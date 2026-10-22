<?php

namespace App\Http\Controllers\Api\Offer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Offer\CreateOfferRequest;
use App\Http\Requests\Offer\RespondOfferRequest;
use App\Http\Resources\OfferResource;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\CandidateProfile;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OfferController extends Controller
{
    /**
     * POST /api/company/offers
     */
    public function store(CreateOfferRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $application = Application::findOrFail($validated['application_id']);

        $validated['sent_by'] = $request->user()->id;
        $validated['status'] = 'sent';
        $validated['sent_at'] = now();

        $offer = DB::transaction(function () use ($validated, $application, $request) {
            $offer = Offer::create($validated);

            $oldStatus = $application->status;
            $application->update(['status' => 'offered']);

            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'from_status'    => $oldStatus,
                'to_status'      => 'offered',
                'changed_by'     => $request->user()->id,
                'reason'         => 'Formal job offer extended.',
            ]);

            return $offer;
        });

        return response()->json([
            'message' => 'Job offer issued successfully.',
            'offer'   => new OfferResource($offer->load('application.job.company')),
        ], 201);
    }

    /**
     * GET /api/candidate/offers
     */
    public function candidateOffers(Request $request): AnonymousResourceCollection
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);

        $offers = Offer::with(['application.job.company'])
            ->whereHas('application', fn ($q) => $q->where('candidate_id', $profile->id))
            ->orderByDesc('sent_at')
            ->get();

        return OfferResource::collection($offers);
    }

    /**
     * GET /api/offers/{id}
     */
    public function show(Request $request, int $id): OfferResource
    {
        $offer = Offer::with(['application.job.company'])->findOrFail($id);

        return new OfferResource($offer);
    }

    /**
     * POST /api/candidate/offers/{id}/accept
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);

        $offer = Offer::with('application')
            ->whereHas('application', fn ($q) => $q->where('candidate_id', $profile->id))
            ->where('status', 'sent')
            ->findOrFail($id);

        DB::transaction(function () use ($offer, $request) {
            $offer->update([
                'status'       => 'accepted',
                'responded_at' => now(),
            ]);

            $application = $offer->application;
            $oldStatus = $application->status;
            $application->update(['status' => 'hired']);

            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'from_status'    => $oldStatus,
                'to_status'      => 'hired',
                'changed_by'     => $request->user()->id,
                'reason'         => 'Job offer accepted by candidate.',
            ]);
        });

        return response()->json([
            'message' => 'Congratulations! You have accepted the offer.',
            'offer'   => new OfferResource($offer->fresh(['application.job.company'])),
        ], 200);
    }

    /**
     * POST /api/candidate/offers/{id}/reject
     */
    public function reject(RespondOfferRequest $request, int $id): JsonResponse
    {
        $profile = CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);

        $offer = Offer::with('application')
            ->whereHas('application', fn ($q) => $q->where('candidate_id', $profile->id))
            ->where('status', 'sent')
            ->findOrFail($id);

        DB::transaction(function () use ($offer, $request) {
            $offer->update([
                'status'       => 'rejected',
                'responded_at' => now(),
            ]);

            $application = $offer->application;
            $oldStatus = $application->status;
            $application->update(['status' => 'rejected']);

            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'from_status'    => $oldStatus,
                'to_status'      => 'rejected',
                'changed_by'     => $request->user()->id,
                'reason'         => 'Job offer declined by candidate. Reason: ' . ($request->validated()['reason'] ?? 'None'),
            ]);
        });

        return response()->json([
            'message' => 'Job offer declined.',
            'offer'   => new OfferResource($offer->fresh(['application.job.company'])),
        ], 200);
    }
}
