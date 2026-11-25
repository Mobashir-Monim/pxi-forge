<?php

namespace App\Domains\Billing\Actions\Query;

use App\Domains\Authorization\Enums\Permission;
use App\Domains\Generation\Enums\GenerationStatus;
use App\Domains\Generation\Models\Generation;
use App\Domains\Generation\Support\Money;
use App\Models\User;
use App\Services\ImageGeneration\Data\UsageDay;
use App\Services\ImageGeneration\ImageGenerationService;
use App\Services\Shared\Exceptions\ProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * `/reports`, for operators: what users were charged, what Pixelforge charged us and the profit, per UTC day and per
 * user. Days are bucketed by completion, as GET /v1/usage bills them, and each day sits beside Pixelforge's own figure
 * so a mismatch shows. Every amount is integer nanodollars, summed exactly and rounded only in the browser.
 */
final class ShowSpendReport
{
    use AsAction;

    /** GET /v1/usage answers at most this many days at once. */
    public const int MAX_DAYS = 92;

    public function __construct(private readonly ImageGenerationService $images) {}

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->can(Permission::ReportsView->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->where('completed_at', '>=', $from->startOfDay())
            ->where('completed_at', '<', $to->addDay()->startOfDay())
            ->groupBy('day', 'user_id')
            ->get([
                DB::raw('cast(completed_at as date) as day'),
                'user_id',
                DB::raw('count(*) as generations'),
                DB::raw('sum(charge_nd) as charged'),
                DB::raw('sum(provider_cost_nd) as cost'),
            ]);

        $names = User::query()->whereIn('id', $rows->pluck('user_id')->unique())->get(['id', 'name', 'email'])->keyBy('id');
        [$usage, $usageError] = $this->usage($from, $to);

        // Every day Pixelforge billed something, so a cost it charged that we never recorded still shows up.
        $days = [];

        foreach ($usage as $date => $remote) {
            if ($remote->generations > 0 || Money::fromUsd($remote->costUsd) > 0) {
                $days[$date] = ['date' => $date, ...self::line(0, 0, 0), 'users' => []];
            }
        }

        $users = [];
        $totals = self::line(0, 0, 0);

        foreach ($rows as $row) {
            $date = (string) $row->getAttribute('day');
            $line = self::line((int) $row->getAttribute('generations'), (int) $row->getAttribute('charged'), (int) $row->getAttribute('cost'));
            $user = $names->get($row->user_id);
            $who = ['id' => $row->user_id, 'name' => $user->name ?? 'Deleted user', 'email' => $user->email ?? ''];

            $days[$date] ??= ['date' => $date, ...self::line(0, 0, 0), 'users' => []];
            $days[$date] = [...$days[$date], ...self::sum($days[$date], $line)];
            $days[$date]['users'][] = [...$who, ...$line];

            $users[$row->user_id] = [...$who, ...self::sum($users[$row->user_id] ?? self::line(0, 0, 0), $line)];
            $totals = self::sum($totals, $line);
        }

        foreach ($days as $date => $day) {
            usort($day['users'], fn (array $a, array $b): int => $b['charged'] <=> $a['charged']);
            $remote = $usage[$date] ?? null;
            $days[$date] = [...$day, 'pixelforge' => $remote === null ? null : ['generations' => $remote->generations, 'cost' => Money::fromUsd($remote->costUsd)]];
        }

        krsort($days);
        $users = array_values($users);
        usort($users, fn (array $a, array $b): int => $b['charged'] <=> $a['charged']);

        $remoteTotal = null;

        if ($usageError === null) {
            $remoteTotal = ['generations' => 0, 'cost' => 0];

            foreach ($usage as $remote) {
                $remoteTotal['generations'] += $remote->generations;
                $remoteTotal['cost'] += Money::fromUsd($remote->costUsd);
            }
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => array_values($days),
            'users' => $users,
            'totals' => [...$totals, 'pixelforge' => $remoteTotal],
            'pixelforge_error' => $usageError,
        ];
    }

    public function asController(ActionRequest $request): Response
    {
        $to = CarbonImmutable::parse($request->validated('to') ?? now('UTC')->toDateString(), 'UTC');
        $from = CarbonImmutable::parse($request->validated('from') ?? $to->subDays(29)->toDateString(), 'UTC');

        // Kept to what Pixelforge will answer for, so every day in range can be checked against it.
        $from = $from->max($to->subDays(self::MAX_DAYS - 1))->min($to);

        return Inertia::render('reports/Index', $this->handle($from, $to));
    }

    /**
     * Pixelforge's billed usage by date. Its outage shouldn't hide our own numbers, so a failure comes back as a message.
     *
     * @return array{0: array<string, UsageDay>, 1: string|null}
     */
    private function usage(CarbonImmutable $from, CarbonImmutable $to): array
    {
        try {
            $days = $this->images->usage($from, $to);
        } catch (ProviderException $e) {
            report($e);

            return [[], "Couldn't load Pixelforge's usage: {$e->getMessage()}"];
        }

        return [array_column(array_map(fn (UsageDay $day): array => ['date' => $day->date, 'day' => $day], $days), 'day', 'date'), null];
    }

    /** @return array{generations: int, charged: int, cost: int, profit: int} */
    private static function line(int $generations, int $charged, int $cost): array
    {
        return ['generations' => $generations, 'charged' => $charged, 'cost' => $cost, 'profit' => $charged - $cost];
    }

    /**
     * @param  array{generations: int, charged: int, cost: int, profit: int}  $a
     * @param  array{generations: int, charged: int, cost: int, profit: int}  $b
     * @return array{generations: int, charged: int, cost: int, profit: int}
     */
    private static function sum(array $a, array $b): array
    {
        return self::line($a['generations'] + $b['generations'], $a['charged'] + $b['charged'], $a['cost'] + $b['cost']);
    }
}
