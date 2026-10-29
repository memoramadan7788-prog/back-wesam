<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyDocumentResource;
use App\Models\Company;
use App\Models\CompanyDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform-admin view of ONE company: full profile, owner, documents, and edit rights.
 * Guarded by `permission:companies.review` (the super admin has it).
 */
class CompanyDetailsController extends Controller
{
    private function find(int $id): Company
    {
        return Company::with(['owner', 'reviewer', 'reviewerAdmin'])
            ->withCount(['jobs', 'companyEmployees'])
            ->findOrFail($id);
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
            'reviewed_at'      => $company->reviewed_at?->toIso8601String(),
            'reviewed_by'      => $company->reviewerAdmin?->name ?? $company->reviewer?->name,
            'jobs_count'       => $company->jobs_count,
            'employees_count'  => $company->company_employees_count,
            'created_at'       => $company->created_at?->toIso8601String(),
            'owner'            => $company->owner ? [
                'id'    => $company->owner->id,
                'name'  => $company->owner->name,
                'email' => $company->owner->email,
            ] : null,
            'documents'        => CompanyDocumentResource::collection(
                $company->documents()->latest()->get()
            )->resolve(),
        ];
    }

    /** GET /api/admin/companies/{id}/details */
    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->find($id))]);
    }

    /** PUT /api/admin/companies/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);

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
            'message' => 'Company updated successfully.',
            'data'    => $this->payload($this->find($id)),
        ]);
    }

    /** POST /api/admin/companies/{id}/documents */
    public function storeDocument(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);

        $validated = $request->validate([
            'file'  => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'type'  => ['nullable', Rule::in(CompanyDocument::TYPES)],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');

        $doc = CompanyDocument::create([
            'company_id'           => $company->id,
            'type'                 => $validated['type'] ?? 'other',
            'title'                => $validated['title'] ?? null,
            'original_filename'    => $file->getClientOriginalName(),
            'storage_path'         => $file->store("company-documents/{$company->id}", 'local'),
            'mime_type'            => $file->getClientMimeType(),
            'size_bytes'           => $file->getSize(),
            'uploaded_by_admin_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'data'    => (new CompanyDocumentResource($doc))->resolve(),
        ], 201);
    }

    /** GET /api/admin/companies/{id}/documents/{docId}/download */
    public function downloadDocument(int $id, int $docId): StreamedResponse
    {
        $doc = CompanyDocument::where('company_id', $id)->findOrFail($docId);

        abort_unless(Storage::disk('local')->exists($doc->storage_path), 404, 'File not found.');

        return Storage::disk('local')->download($doc->storage_path, $doc->original_filename);
    }

    /** DELETE /api/admin/companies/{id}/documents/{docId} */
    public function destroyDocument(int $id, int $docId): JsonResponse
    {
        CompanyDocument::where('company_id', $id)->findOrFail($docId)->delete();

        return response()->json(['message' => 'Document deleted successfully.']);
    }
}
