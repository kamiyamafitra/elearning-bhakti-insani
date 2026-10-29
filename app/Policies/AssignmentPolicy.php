<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;

class AssignmentPolicy
{
    public function view(User $user, Assignment $assignment): bool
    {
        if ($user->isTeacher()) {
            return $this->owns($user, $assignment)
                && $this->teaches($user, (int) $assignment->subject_id, (int) $assignment->class_id);
        }

        if ($user->isStudent()) {
            return $assignment->isPublished()
                && $user->studentClassId() === (int) $assignment->class_id;
        }

        return false;
    }

    public function create(User $user, Subject $subject, int $classId): bool
    {
        return $user->isTeacher() && $this->teaches($user, (int) $subject->id, $classId);
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $user->isTeacher()
            && $this->owns($user, $assignment)
            && $this->teaches($user, (int) $assignment->subject_id, (int) $assignment->class_id);
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    public function submit(User $user, Assignment $assignment): bool
    {
        return $user->isStudent()
            && $assignment->isPublished()
            && $user->studentClassId() === (int) $assignment->class_id;
    }

    public function downloadAttachment(User $user, Assignment $assignment): bool
    {
        return $this->view($user, $assignment);
    }

    public function viewSubmission(User $user, Assignment $assignment, AssignmentSubmission $submission): bool
    {
        if ((int) $submission->assignment_id !== (int) $assignment->id) {
            return false;
        }

        if ($user->isStudent()) {
            return (int) $submission->student_id === (int) $user->id;
        }

        if ($user->isTeacher()) {
            return $this->owns($user, $assignment);
        }

        return false;
    }

    public function downloadSubmission(User $user, Assignment $assignment, AssignmentSubmission $submission): bool
    {
        return $this->viewSubmission($user, $assignment, $submission);
    }

    public function grade(User $user, Assignment $assignment): bool
    {
        return $user->isTeacher()
            && $this->owns($user, $assignment)
            && $this->teaches($user, (int) $assignment->subject_id, (int) $assignment->class_id);
    }

    private function owns(User $user, Assignment $assignment): bool
    {
        return (int) $assignment->teacher_id === (int) $user->id;
    }

    private function teaches(User $user, int $subjectId, int $classId): bool
    {
        return TeachingAssignment::query()
            ->where('teacher_id', $user->id)
            ->where('subject_id', $subjectId)
            ->where('class_id', $classId)
            ->exists();
    }
}
