<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\HealthFollowUp;
use App\Models\VetExamination;
use App\Services\Health\HealthAttachmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Files on vet visits, examinations, cases and follow-ups. Reading a file
 * needs the same access as reading the record it belongs to; another farm's
 * records and files are not found.
 */
class HealthAttachmentController extends Controller
{
    public function store(Request $request, HealthAttachmentService $files, string $type, int $id)
    {
        $parent = $this->parent($type, $id);
        abort_unless($files->canAdd($request->user(), $parent), 403);
        $max = (int) config('health.attachments.max_size_mb', 10) * 1024;
        $data = $request->validate(['file' => ['required', 'file', "max:{$max}"], 'title' => ['nullable', 'string', 'max:255']],
            ['file.max' => 'Files can be up to '.config('health.attachments.max_size_mb', 10).' MB.']);
        $files->add($request->user(), $parent, $data['file'], $data['title'] ?? null);

        return back()->with('success', 'File attached.');
    }

    public function download(Attachment $attachment, HealthAttachmentService $files)
    {
        $this->parentOf($attachment);

        return $files->stream($attachment);
    }

    public function remove(Request $request, Attachment $attachment, HealthAttachmentService $files)
    {
        $this->parentOf($attachment);
        abort_unless($request->user()->hasPermission('correct_health_records'), 403);
        $data = $request->validate(['remove_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $files->remove($request->user(), $attachment, $data['remove_reason']);

        return back()->with('success', 'File removed. It is kept for the record.');
    }

    private function parent(string $type, int $id): Model
    {
        $class = HealthAttachmentService::PARENTS[$type] ?? abort(404);
        $parent = $class::query()->findOrFail($id);
        $this->authorizeView($parent);

        return $parent;
    }

    /** The attachment must belong to a health record this user can see. */
    private function parentOf(Attachment $attachment): Model
    {
        $type = array_search($attachment->attachable_type, HealthAttachmentService::PARENTS, true);
        abort_if($type === false, 404);

        return $this->parent($type, (int) $attachment->attachable_id);
    }

    private function authorizeView(Model $parent): void
    {
        match (true) {
            $parent instanceof VetExamination => $this->authorize('view', $parent->visit),
            $parent instanceof HealthFollowUp => $this->authorize('view', $parent->healthRecord),
            default => $this->authorize('view', $parent),
        };
    }
}
