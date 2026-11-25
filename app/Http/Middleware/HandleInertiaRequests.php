<?php

namespace App\Http\Middleware;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Billing\Actions\Command\BuyCredits;
use App\Domains\Billing\Actions\Query\GetCreditBalance;
use App\Domains\Generation\Models\Chat;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user()?->permissionNames() ?? [],
            ],
            // Lazy, so a partial reload that doesn't ask for it skips the query.
            'chats' => fn (): array => $request->user()?->can(Permission::ChatsView->value)
                ? Chat::query()->whereBelongsTo($request->user())->latest('updated_at')->limit(50)
                    ->get(['id', 'title', 'status'])
                    ->map(fn (Chat $chat): array => ['id' => $chat->id, 'title' => $chat->title, 'archived' => $chat->isArchived()])
                    ->all()
                : [],
            // Lazy too, and fresh on every visit, so a finished image or a purchase shows in the balance straight away.
            'credits' => fn (): ?array => $request->user()?->can(Permission::CreditsView->value)
                ? [...GetCreditBalance::run($request->user()), 'packs' => BuyCredits::PACKS_CENTS]
                : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
