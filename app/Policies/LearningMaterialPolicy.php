<?php

namespace App\Policies;

use App\Models\LearningMaterial;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;

class LearningMaterialPolicy
{
    public function view(User $user, LearningMaterial $material): bool
    {
        if ($user->isTeacher()) {
            return $this->teaches($user, (int) $material->subject_id, (int) $material->class_id);
        }

        if ($user->isStudent()) {
            return $material->isPublished()
                && $user->studentClassId() === (int) $material->class_id;
        }

        return false;
    }

    public function create(User $user, Subject $subject, int $classId): bool
    {
        return $user->isTeacher() && $this->teaches($user, (int) $subject->id, $classId);
    }

    public function update(User $user, LearningMaterial $material): bool
    {
        return $user->isTeacher()
            && (int) $material->teacher_id === (int) $user->id
            && $this->teaches($user, (int) $material->subject_id, (int) $material->class_id);
    }

    public function delete(User $user, LearningMaterial $material): bool
    {
        return $this->update($user, $material);
    }

    public function download(User $user, LearningMaterial $material): bool
    {
        return $this->view($user, $material);
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
