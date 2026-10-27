<?php
// app/Services/Teb/TebUser.php

namespace App\Services\Teb;

// The logged-in employee as the TEB portal sees them (slide 2 "Project Owner").
// AuthMiddleware fills session('emp_data'). Anyone signed in can register a project;
// only Process Engineering (config teb.admin_departments) is TEB Admin.
class TebUser
{
    public static function current(): ?TebActor
    {
        return TebActor::fromEmpData(session('emp_data'));
    }

    public static function required(): TebActor
    {
        $user = static::current();
        if (! $user) {
            session()->forget('emp_data');
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                back()->with('error', 'Your session has expired. Please refresh the page and log in again.')
            );
        }

        return $user;
    }
    public static function isAdmin(?TebActor $user = null): bool
    {
        $user = $user ?: static::current();

        if (! $user || ! $user->dept) {
            return false;
        }

        $dept = mb_strtolower(trim($user->dept));

        foreach (config('teb.admin_departments', []) as $allowed) {
            if (mb_strtolower(trim($allowed)) === $dept) {
                return true;
            }
        }

        return false;
    }
}
