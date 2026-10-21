<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\FxRate;
use App\Models\PriceRate;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformRateController extends Controller
{
    /**
     * Display price rates and foreign exchange rates management.
     */
    public function index(): View
    {
        $priceRates = PriceRate::orderBy('effective_from', 'desc')->get();
        $fxRates = FxRate::orderBy('date', 'desc')->take(30)->get();

        return view('platform.rates', [
            'priceRates' => $priceRates,
            'fxRates' => $fxRates,
        ]);
    }

    /**
     * Store a new service price rate.
     */
    public function storePriceRate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:50'],
            'model' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'in:detik_audio,token,gb_bulan,byte_hari'],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
        ], [
            'provider.required' => 'Nama penyedia wajib diisi.',
            'model.required' => 'Nama model/layanan wajib diisi.',
            'unit.required' => 'Satuan tarif wajib dipilih.',
            'price_usd.required' => 'Tarif USD wajib diisi.',
            'effective_from.required' => 'Tanggal mulai berlaku tarif wajib ditentukan.',
        ]);

        $rate = PriceRate::create([
            'provider' => trim($validated['provider']),
            'model' => trim($validated['model']),
            'unit' => $validated['unit'],
            'price_usd' => (float) $validated['price_usd'],
            'effective_from' => $validated['effective_from'],
        ]);

        Audit::record('platform_rate_create', null, [
            'price_rate_id' => $rate->id,
            'provider' => $rate->provider,
            'model' => $rate->model,
            'unit' => $rate->unit,
            'price_usd' => $rate->price_usd,
            'effective_from' => $rate->effective_from->toIso8601String(),
            'ip' => $request->ip(),
        ]);

        return back()->with('success', "Tarif baru untuk {$rate->provider} ({$rate->model}) berhasil disimpan.");
    }

    /**
     * Store or update daily USD/IDR exchange rate.
     */
    public function storeFxRate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'usd_idr' => ['required', 'numeric', 'min:1'],
        ], [
            'date.required' => 'Tanggal kurs wajib ditentukan.',
            'usd_idr.required' => 'Nilai kurs USD/IDR wajib diisi.',
        ]);

        $fx = FxRate::updateOrCreate(
            ['date' => $validated['date']],
            ['usd_idr' => (float) $validated['usd_idr']]
        );

        Audit::record('platform_fx_update', null, [
            'fx_rate_id' => $fx->id,
            'date' => $fx->date->toDateString(),
            'usd_idr' => $fx->usd_idr,
            'ip' => $request->ip(),
        ]);

        return back()->with('success', "Kurs USD/IDR untuk tanggal {$fx->date->format('d M Y')} berhasil disimpan: Rp ".number_format($fx->usd_idr, 2));
    }
}
