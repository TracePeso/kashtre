@php
    $business = $transaction->business ?? $transaction->member?->business ?? null;
    $branding = $branding ?? \App\Support\BusinessBranding::for($business);
    $documentTitle = 'Transaction Receipt';
    $generatedAt = $generatedAt ?? now();
@endphp
@extends('layouts.pdf')

@section('title', $documentTitle)

@push('styles')
<style>
    .content { margin: 16px 0; }
    .content p { margin: 5px 0; }
</style>
@endpush

@section('content')
    <div class="content">
        <p>Dear <strong>{{ $transaction->member->name }}</strong>,</p>
        <p>We are pleased to confirm that your transaction has been successfully processed. Below are the details of your transaction:</p>
    </div>

    <table>
        <tr>
            <th>Transaction Reference</th>
            <td>{{ $transaction->transaction_reference }}</td>
        </tr>
        <tr>
            <th>Amount</th>
            <td>UGX {{ number_format($transaction->amount, 2) }}</td>
        </tr>
        <tr>
            <th>Date</th>
            <td>{{ $transaction->created_at->format('d M Y, H:i A') }}</td>
        </tr>
    </table>
@endsection
