<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DocumentOverviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $documentsByTrip = Document::query()
            ->with(['trip', 'booking'])
            ->whereHas('trip', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->orWhereHas('sharedUsers', fn ($sharedQuery) => $sharedQuery->whereKey($request->user()->id)))
            ->get()
            ->groupBy('trip_id')
            ->sortBy(fn (Collection $documents) => $this->tripSortKey($documents->first()->trip))
            ->map(fn (Collection $documents) => $documents->sortByDesc('created_at')->values());

        return view('documents.index', [
            'documentsByTrip' => $documentsByTrip,
        ]);
    }

    private function tripSortKey($trip): string
    {
        $startDate = $trip->start_date;

        if ($startDate && $startDate->isFuture()) {
            return '0-'.$startDate->format('Ymd').'-'.strtolower($trip->title);
        }

        if ($startDate && $trip->is_active) {
            return '0-'.$startDate->format('Ymd').'-'.strtolower($trip->title);
        }

        if ($startDate) {
            return '1-'.$startDate->format('Ymd').'-'.strtolower($trip->title);
        }

        return '2-99999999-'.strtolower($trip->title);
    }
}
