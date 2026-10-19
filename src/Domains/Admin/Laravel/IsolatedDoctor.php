<?php

namespace LaRecipe\Domains\Admin\Laravel;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use LaRecipe\Domains\Agents\Actions\ExamineReadinessAction;
use LaRecipe\Domains\Agents\DTOs\DoctorReport;
use LaRecipe\Domains\Content\DTOs\DocSet;

/**
 * Runs the larecipe:doctor report from inside an admin request.
 *
 * The doctor grades the docs the way an agent sees them, by sending requests
 * through the app's HTTP kernel. Inside a web request that would leave the
 * probes' state behind: the shared session store would end up holding a
 * probe's empty session (signing the reviewer out), and the probes would read
 * the docs as the signed-in reviewer instead of as an agent. So the probes
 * run as a guest, and the request, session and user are put back afterwards.
 */
final class IsolatedDoctor
{
    public function __construct(
        private readonly Container $app,
        private readonly Auth $auth,
        private readonly ExamineReadinessAction $doctor,
    ) {}

    public function examine(DocSet $set): DoctorReport
    {
        $request = $this->app->make('request');
        $session = $request instanceof Request && $request->hasSession() ? $request->session() : null;
        $id = $session?->getId();
        $attributes = $session?->all() ?? [];
        $guard = $this->auth->guard();
        $user = $guard->user();

        if (method_exists($guard, 'forgetUser')) {
            $guard->forgetUser();
        }

        try {
            return $this->doctor->execute($set);
        } finally {
            $this->app->instance('request', $request);
            $this->restore($session, $id, $attributes);

            if ($user !== null && method_exists($guard, 'setUser')) {
                $guard->setUser($user);
            }
        }
    }

    /** @param array<string, mixed> $attributes */
    private function restore(?Session $session, ?string $id, array $attributes): void
    {
        if ($session === null || $id === null) {
            return;
        }

        $session->setId($id);
        $session->flush();
        $session->put($attributes);
    }
}
