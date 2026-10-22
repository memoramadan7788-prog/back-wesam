<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @mixin \App\Models\Interview
 */
class InterviewerInterviewResource extends JsonResource
{
    /**
     * Transform the resource into an array (ERD Page 4).
     * Exposes candidate summary and application-linked CV metadata without models or raw storage paths.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Resolve candidate and application-specific CV metadata via Query Builder if not preloaded
        $candidateData = $this->candidateData ?? (
            ($this->application_id && Schema::hasTable('applications')) ? DB::table('applications')
                ->when(Schema::hasTable('candidate_profiles'), fn($q) => $q->leftJoin('candidate_profiles', 'candidate_profiles.id', '=', 'applications.candidate_id'))
                ->when(Schema::hasTable('users') && Schema::hasTable('candidate_profiles'), fn($q) => $q->leftJoin('users', 'users.id', '=', 'candidate_profiles.user_id'))
                ->when(Schema::hasTable('candidate_cvs'), fn($q) => $q->leftJoin('candidate_cvs', 'candidate_cvs.id', '=', 'applications.cv_id'))
                ->where('applications.id', $this->application_id)
                ->select([
                    'applications.id as application_id',
                    'applications.candidate_id',
                    'applications.cv_id',
                    (Schema::hasTable('users') && Schema::hasColumn('users', 'id')) ? 'users.id as user_id' : DB::raw('NULL as user_id'),
                    (Schema::hasTable('users') && Schema::hasColumn('users', 'name')) ? 'users.name as candidate_name' : DB::raw('NULL as candidate_name'),
                    (Schema::hasTable('users') && Schema::hasColumn('users', 'email')) ? 'users.email as candidate_email' : DB::raw('NULL as candidate_email'),
                    (Schema::hasTable('candidate_profiles') && Schema::hasColumn('candidate_profiles', 'phone')) ? 'candidate_profiles.phone as candidate_phone' : DB::raw('NULL as candidate_phone'),
                    (Schema::hasTable('candidate_profiles') && Schema::hasColumn('candidate_profiles', 'headline')) ? 'candidate_profiles.headline as candidate_headline' : DB::raw('NULL as candidate_headline'),
                    (Schema::hasTable('candidate_cvs') && Schema::hasColumn('candidate_cvs', 'original_filename')) ? 'candidate_cvs.original_filename as cv_filename' : DB::raw('NULL as cv_filename'),
                    (Schema::hasTable('candidate_cvs') && Schema::hasColumn('candidate_cvs', 'mime_type')) ? 'candidate_cvs.mime_type as cv_mime_type' : DB::raw('NULL as cv_mime_type'),
                    (Schema::hasTable('candidate_cvs') && Schema::hasColumn('candidate_cvs', 'size_bytes')) ? 'candidate_cvs.size_bytes as cv_size_bytes' : DB::raw('NULL as cv_size_bytes'),
                    (Schema::hasTable('candidate_cvs') && Schema::hasColumn('candidate_cvs', 'uploaded_at')) ? 'candidate_cvs.uploaded_at as cv_uploaded_at' : DB::raw('NULL as cv_uploaded_at'),
                ])
                ->first() : null
        );

        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'round_no' => $this->round_no,
            'interview_type' => $this->interview_type,
            'status' => $this->status,
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
            'instructions' => (Schema::hasTable('interviews') && Schema::hasColumn('interviews', 'instructions'))
                ? $this->instructions
                : null,
            'candidate' => $candidateData ? [
                'name' => $candidateData->candidate_name,
                'email' => $candidateData->candidate_email,
                'phone' => $candidateData->candidate_phone,
                'headline' => $candidateData->candidate_headline,
                'cv' => $candidateData->cv_id ? [
                    'cv_id' => $candidateData->cv_id,
                    'filename' => $candidateData->cv_filename,
                    'mime_type' => $candidateData->cv_mime_type,
                    'size_bytes' => $candidateData->cv_size_bytes,
                    'uploaded_at' => $candidateData->cv_uploaded_at,
                ] : null,
            ] : null,
            'bookings' => $this->whenLoaded('bookings', function () {
                return $this->bookings->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'interviewer_id' => $booking->interviewer_id,
                        'availability_id' => $booking->availability_id,
                        'start_at' => $booking->start_at?->toIso8601String(),
                        'end_at' => $booking->end_at?->toIso8601String(),
                        'booking_status' => $booking->booking_status,
                        'interviewer' => $booking->relationLoaded('interviewer') && $booking->interviewer ? [
                            'id' => $booking->interviewer->id,
                            'name' => $booking->interviewer->name,
                            'email' => $booking->interviewer->email,
                        ] : null,
                    ];
                });
            }),
            'evaluations' => InterviewEvaluationResource::collection($this->whenLoaded('evaluations')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
