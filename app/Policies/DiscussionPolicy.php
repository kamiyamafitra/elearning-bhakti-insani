<?php

namespace App\Policies;

use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\TeachingAssignment;
use App\Models\User;

class DiscussionPolicy
{
    public function view(User $user, Discussion $discussion): bool
    {
        return $this->create($user, (int) $discussion->class_id, (int) $discussion->subject_id);
    }

    public function create(User $user, int $classId, int $subjectId): bool
    {
        if ($user->isTeacher()) {
            return $this->teaches($user, $classId, $subjectId);
        }

        return $user->isStudent()
            && $user->studentClassId() === $classId
            && TeachingAssignment::query()->where('class_id', $classId)->where('subject_id', $subjectId)->exists();
    }

    public function reply(User $user, Discussion $discussion): bool
    {
        return ! $discussion->is_closed && $this->view($user, $discussion);
    }

    /**
     * Pinning, closing, and choosing the best answer belong to the subject's teachers.
     */
    public function moderate(User $user, Discussion $discussion): bool
    {
        return $user->isTeacher() && $this->teaches($user, (int) $discussion->class_id, (int) $discussion->subject_id);
    }

    public function delete(User $user, Discussion $discussion): bool
    {
        return $this->view($user, $discussion)
            && ((int) $discussion->user_id === (int) $user->id || $this->moderate($user, $discussion));
    }

    public function deleteReply(User $user, Discussion $discussion, DiscussionReply $reply): bool
    {
        return $this->view($user, $discussion)
            && ((int) $reply->user_id === (int) $user->id || $this->moderate($user, $discussion));
    }

    private function teaches(User $user, int $classId, int $subjectId): bool
    {
        return TeachingAssignment::query()
            ->where('teacher_id', $user->id)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->exists();
    }
}
