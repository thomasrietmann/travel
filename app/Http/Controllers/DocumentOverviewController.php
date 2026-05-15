<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
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
            ->sortBy([
                fn (Document $first, Document $second) => strcasecmp($first->trip->title, $second->trip->title),
                fn (Document $first, Document $second) => $second->created_at <=> $first->created_at,
            ])
            ->groupBy('trip_id');

        return view('documents.index', [
            'documentsByTrip' => $documentsByTrip,
        ]);
    }
}
