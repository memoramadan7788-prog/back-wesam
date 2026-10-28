<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyDocumentResource;
use App\Models\Admin;
use App\Models\Company;
use App\Models\CompanyDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Company-side profile + documents.
 * These routes are NOT behind `company.active`: a company that is still pending
 * must be able to complete its profile and upload its papers so the admin can review them.
 */
class CompanyProfileController extends Controller
{
    private function company(Request $request): Company
    {
        $user = $request->user();

        abort_if($user instanceof Admin, 403, 'Platform admins manage companies through /api/admin/companies.');

        $company = $user->resolveCompany();
        abort_if(! $company, 403, 'You are not associated with any company.');

        return $company;
    }

    /** Only the owner or a user allowed to manage the company can change it. */
    private function authorizeManage(Request $request, Company $company): void
    {
        $user = $request->user();
        $isOwner = (int) $company->owner_user_id === (int) $user->id;

        abort_unless(
            $isOwner || $user->hasPermissionForCompany('employees.manage', $company->id),
            403,
            'Only the company owner or an administrator of the company can do this.'
        );
    }

    private function payload(Company $company): array
    {
        return [
            'id'               => $company->id,
            'name'             => $company->name,
            'website'          => $company->website,
            'industry'         => $company->industry,
            'contact_email'    => $company->contact_email,
            'contact_phone'    => $company->contact_phone,
            'address'          => $company->address,
            'status'           => $company->status,
            'rejection_reason' => $company->rejection_reason,
            'documents'        => CompanyDocumentResource::collection(
                $company->documents()->latest()->get()
            )->resolve(),
        ];
    }

    /** GET /api/company/profile */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->company($request))]);
    }

    /** PUT /api/company/profile */
    public function update(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $this->authorizeManage($request, $company);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'website'       => ['nullable', 'url', 'max:2048'],
            'industry'      => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'address'       => ['nullable', 'string'],
        ]);

        $company->update($data);

        return response()->json([
            'message' => 'Company profile updated successfully.',
            'data'    => $this->payload($company->fresh()),
        ]);
    }

    /** GET /api/company/documents */
    public function documents(Request $request): JsonResponse
    {
        $company = $this->company($request);

        return response()->json([
            'data' => CompanyDocumentResource::collection($company->documents()->latest()->get())->resolve(),
        ]);
    }

    /** POST /api/company/documents  (multipart: file, type, title) */
    public function storeDocument(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $this->authorizeManage($request, $company);

        $validated = $request->validate([
            'file'  => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'type'  => ['nullable', Rule::in(CompanyDocument::TYPES)],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');

        $doc = CompanyDocument::create([
            'company_id'          => $company->id,
            'type'                => $validated['type'] ?? 'other',
            'title'               => $validated['title'] ?? null,
            'original_filename'   => $file->getClientOriginalName(),
            'storage_path'        => $file->store("company-documents/{$company->id}", 'local'),
            'mime_type'           => $file->getClientMimeType(),
            'size_bytes'          => $file->getSize(),
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'data'    => (new CompanyDocumentResource($doc))->resolve(),
        ], 201);
    }

    /** GET /api/company/documents/{id}/download */
    public function downloadDocument(Request $request, int $id): StreamedResponse
    {
        $company = $this->company($request);
        $doc = $company->documents()->findOrFail($id);

        abort_unless(Storage::disk('local')->exists($doc->storage_path), 404, 'File not found.');

        return Storage::disk('local')->download($doc->storage_path, $doc->original_filename);
    }

    /** DELETE /api/company/documents/{id} */
    public function destroyDocument(Request $request, int $id): JsonResponse
    {
        $company = $this->company($request);
        $this->authorizeManage($request, $company);

        $company->documents()->findOrFail($id)->delete();

        return response()->json(['message' => 'Document deleted successfully.']);
    }
}
