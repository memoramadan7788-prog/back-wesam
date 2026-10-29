<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdateCandidateNotificationSettingsRequest;
use App\Http\Requests\Candidate\UpdateCandidatePreferencesRequest;
use App\Http\Requests\Candidate\UpdateCandidatePrivacyRequest;
use App\Http\Requests\Candidate\UpdateOnboardingRequest;
use App\Models\CandidateNotificationSetting;
use App\Models\CandidatePreference;
use App\Models\CandidatePrivacySetting;
use App\Models\CandidateProfile;
use App\Models\CandidateOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateSettingsController extends Controller
{
    public function onboarding(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $onboarding = CandidateOnboarding::firstOrCreate(
            ['candidate_id' => $profile->id],
            ['steps' => $this->defaultSteps(), 'completion_percent' => 0]
        );

        return response()->json(['data' => $this->onboardingData($onboarding)], 200);
    }

    public function updateOnboarding(UpdateOnboardingRequest $request): JsonResponse
    {
        $profile = $this->profile($request);
        $onboarding = CandidateOnboarding::firstOrCreate(['candidate_id' => $profile->id]);
        $steps = array_replace_recursive($this->defaultSteps(), $onboarding->steps ?? [], $request->validated('steps'));
        $completionPercent = $this->completionPercent($steps);

        $onboarding->update([
            'steps' => $steps,
            'completion_percent' => $completionPercent,
        ]);

        return response()->json(['data' => $this->onboardingData($onboarding->fresh())], 200);
    }

    public function preferences(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $preferences = CandidatePreference::firstOrCreate(['candidate_id' => $profile->id]);

        return response()->json(['data' => $this->preferencesData($preferences)], 200);
    }

    public function updatePreferences(UpdateCandidatePreferencesRequest $request): JsonResponse
    {
        $profile = $this->profile($request);
        $preferences = CandidatePreference::firstOrCreate(['candidate_id' => $profile->id]);
        $preferences->update($request->validated());

        return response()->json(['data' => $this->preferencesData($preferences->fresh())], 200);
    }

    public function notificationSettings(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $settings = CandidateNotificationSetting::firstOrCreate(['candidate_id' => $profile->id]);

        return response()->json(['data' => $this->notificationData($settings)], 200);
    }

    public function updateNotificationSettings(UpdateCandidateNotificationSettingsRequest $request): JsonResponse
    {
        $profile = $this->profile($request);
        $settings = CandidateNotificationSetting::firstOrCreate(['candidate_id' => $profile->id]);
        $settings->update($request->validated());

        return response()->json(['data' => $this->notificationData($settings->fresh())], 200);
    }

    public function privacy(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $settings = CandidatePrivacySetting::firstOrCreate(['candidate_id' => $profile->id]);

        return response()->json(['data' => $this->privacyData($settings)], 200);
    }

    public function updatePrivacy(UpdateCandidatePrivacyRequest $request): JsonResponse
    {
        $profile = $this->profile($request);
        $settings = CandidatePrivacySetting::firstOrCreate(['candidate_id' => $profile->id]);
        $settings->update($request->validated());

        return response()->json(['data' => $this->privacyData($settings->fresh())], 200);
    }

    private function profile(Request $request): CandidateProfile
    {
        return CandidateProfile::firstOrCreate(['user_id' => $request->user()->id]);
    }

    private function defaultSteps(): array
    {
        return [
            'basics' => ['completed' => false],
            'resume_import' => ['completed' => false],
            'skills_preferences' => ['completed' => false],
        ];
    }

    private function completionPercent(array $steps): int
    {
        $stepNames = ['basics', 'resume_import', 'skills_preferences'];
        $completed = collect($stepNames)
            ->filter(fn (string $step) => (bool) data_get($steps, "{$step}.completed", false))
            ->count();

        return (int) round(($completed / count($stepNames)) * 100);
    }

    private function onboardingData(CandidateOnboarding $onboarding): array
    {
        return [
            'steps' => $onboarding->steps ?? $this->defaultSteps(),
            'completion_percent' => (int) $onboarding->completion_percent,
        ];
    }

    private function preferencesData(CandidatePreference $preferences): array
    {
        return [
            'desired_titles' => $preferences->desired_titles ?? [],
            'location' => $preferences->location,
            'employment_type' => $preferences->employment_type,
            'min_salary' => $preferences->min_salary,
            'work_model' => $preferences->work_model,
            'open_to_relocation' => (bool) $preferences->open_to_relocation,
            'include_agency' => (bool) $preferences->include_agency,
        ];
    }

    private function notificationData(CandidateNotificationSetting $settings): array
    {
        return [
            'weekly_digest' => (bool) $settings->weekly_digest,
            'status_updates' => (bool) $settings->status_updates,
            'interview_reminders' => (bool) $settings->interview_reminders,
            'messages' => (bool) $settings->messages,
            'marketing' => (bool) $settings->marketing,
        ];
    }

    private function privacyData(CandidatePrivacySetting $settings): array
    {
        return [
            'visibility' => $settings->visibility,
            'allow_agency_recruiters' => (bool) $settings->allow_agency_recruiters,
            'show_in_public_search' => (bool) $settings->show_in_public_search,
        ];
    }
}
