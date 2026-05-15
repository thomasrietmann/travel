<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentOverviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $documents = Document::query()
            ->with(['trip', 'booking'])
            ->whereHas('trip', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->orWhereHas('sharedUsers', fn ($sharedQuery) => $sharedQuery->whereKey($request->user()->id)))
            ->latest()
            ->get();

        return view('documents.index', [
            'documents' => $documents,
        ]);
    }
}
