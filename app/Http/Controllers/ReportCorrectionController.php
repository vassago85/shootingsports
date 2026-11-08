<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Http\Requests\StoreCorrectionRequest;
use App\Models\Event;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;

class ReportCorrectionController extends Controller
{
    public function __invoke(StoreCorrectionRequest $request): RedirectResponse
    {
        $subject = $request->subject();
        $type = SubmissionType::from((string) $request->input('type'));

        Submission::query()->create([
            'type' => $type,
            'status' => SubmissionStatus::Pending,
            'submitter_name' => $request->string('submitter_name')->toString(),
            'submitter_email' => $request->string('submitter_email')->toString(),
            'payload' => [
                'reason' => $request->string('reason')->toString(),
                'details' => $request->string('details')->toString(),
                'subject_id' => $subject->getKey(),
                'subject_label' => $subject instanceof Event ? $subject->title : $subject->name,
            ],
        ]);

        return back()->with('status', 'Thanks. The correction is in the queue.');
    }
}
