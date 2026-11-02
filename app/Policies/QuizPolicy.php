<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;

class QuizPolicy
{
    public function view(User $user, Quiz $quiz): bool
    {
        if ($user->isTeacher()) {
            return $this->manages($user, $quiz);
        }

        if ($user->isStudent()) {
            return $quiz->isPublished()
                && $user->studentClassId() === (int) $quiz->class_id
                && ($quiz->isOpen() || $quiz->attempts()->where('student_id', $user->id)->exists());
        }

        return false;
    }

    public function create(User $user, Subject $subject, int $classId): bool
    {
        return $user->isTeacher() && $this->teaches($user, (int) $subject->id, $classId);
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $user->isTeacher() && $this->manages($user, $quiz);
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $this->update($user, $quiz);
    }

    private function manages(User $user, Quiz $quiz): bool
    {
        return (int) $quiz->teacher_id === (int) $user->id
            && $this->teaches($user, (int) $quiz->subject_id, (int) $quiz->class_id);
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
