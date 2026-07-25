<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    /**
     * Display a listing of quotations
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $business = $user->business;
        
        $query = Quotation::where('business_id', $business->id)
            ->with(['invoice', 'client', 'branch', 'createdBy']);
        
        // Filter by status if specified
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }
        
        // Filter by date range if specified
        if ($request->has('date_filter') && $request->date_filter !== '') {
            switch ($request->date_filter) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                    break;
                case 'year':
                    $query->whereYear('created_at', now()->year);
                    break;
            }
        }
        
        // Search functionality
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('quotation_number', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%")
                  ->orWhere('client_phone', 'like', "%{$search}%");
            });
        }
        
        $quotations = $query->orderBy('created_at', 'desc')->paginate(20);
        
        return view('quotations.index', compact('quotations'));
    }
    
    /**
     * Display the specified quotation
     */
    public function show(Quotation $quotation)
    {
        $user = Auth::user();
        
        // Check if user has access to this quotation
        if ($quotation->business_id !== $user->business_id) {
            abort(403, 'Unauthorized access to quotation.');
        }
        
        return view('quotations.show', compact('quotation'));
    }
    
    /**
     * Print quotation
     */
    public function print(Quotation $quotation)
    {
        $user = Auth::user();
        
        // Check if user has access to this quotation
        if ($quotation->business_id !== $user->business_id) {
            abort(403, 'Unauthorized access to quotation.');
        }

        $quotation->load(['business', 'invoice']);

        return view('quotations.print', \App\Support\DocumentViewData::merge(compact('quotation')));
    }
    
    /**
     * Generate quotation from invoice
     */
    public function generateFromInvoice(Request $request, Invoice $invoice)
    {
        try {
            DB::beginTransaction();
            
            $user = Auth::user();
            
            // Check if user has access to this invoice
            if ($invoice->business_id !== $user->business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to invoice.'
                ], 403);
            }
            
            // Generate quotation number
            $quotationNumber = Quotation::generateQuotationNumber($user->business->id);
            
            // Set validity period (default 30 days)
            $validUntil = now()->addDays(30);
            
            // Create quotation from invoice data
            $quotation = Quotation::create([
                'quotation_number' => $quotationNumber,
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'business_id' => $invoice->business_id,
                'branch_id' => $invoice->branch_id,
                'created_by' => $user->id,
                'client_name' => $invoice->client_name,
                'client_phone' => $invoice->client_phone,
                'payment_phone' => $invoice->payment_phone,
                'visit_id' => $invoice->visit_id,
                'items' => $invoice->items,
                'subtotal' => $invoice->subtotal,
                'package_adjustment' => $invoice->package_adjustment,
                'account_balance_adjustment' => $invoice->account_balance_adjustment,
                'service_charge' => $invoice->service_charge,
                'total_amount' => $invoice->total_amount,
                'payment_methods' => $invoice->payment_methods,
                'notes' => $invoice->notes,
                'status' => 'draft',
                'valid_until' => $validUntil,
                'generated_at' => now(),
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Quotation generated successfully',
                'quotation_number' => $quotationNumber,
                'quotation_id' => $quotation->id,
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate quotation: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Update quotation status
     */
    public function updateStatus(Request $request, Quotation $quotation)
    {
        $user = Auth::user();
        
        // Check if user has access to this quotation
        if ($quotation->business_id !== $user->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to quotation.'
            ], 403);
        }
        
        $validated = $request->validate([
            'status' => 'required|in:draft,sent,accepted,rejected,expired',
        ]);
        
        try {
            $quotation->update(['status' => $validated['status']]);
            
            return response()->json([
                'success' => true,
                'message' => 'Quotation status updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update quotation status: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Accept quotation
     */
    public function accept(Quotation $quotation)
    {
        $user = Auth::user();
        
        // Check if user has access to this quotation
        if ($quotation->business_id !== $user->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to quotation.'
            ], 403);
        }
        
        try {
            $quotation->markAsAccepted();
            
            return response()->json([
                'success' => true,
                'message' => 'Quotation accepted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept quotation: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Reject quotation
     */
    public function reject(Quotation $quotation)
    {
        $user = Auth::user();
        
        // Check if user has access to this quotation
        if ($quotation->business_id !== $user->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to quotation.'
            ], 403);
        }
        
        try {
            $quotation->markAsRejected();
            
            return response()->json([
                'success' => true,
                'message' => 'Quotation rejected successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject quotation: ' . $e->getMessage()
            ], 500);
        }
    }

}
