<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Business;
use App\Mail\ClientReceipt;
use App\Mail\BusinessReceipt;
use App\Mail\KashTreReceipt;
use App\Services\DocumentPdfService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ReceiptService
{
    public function __construct(
        private readonly DocumentPdfService $documents,
    ) {
    }

    /**
     * Send electronic receipts to all parties upon payment clearance
     */
    public function sendElectronicReceipts(Invoice $invoice)
    {
        try {
            Log::info("=== SENDING ELECTRONIC RECEIPTS STARTED ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_id' => $invoice->client_id,
                'business_id' => $invoice->business_id,
                'client_name' => $invoice->client->name ?? 'unknown',
                'business_name' => $invoice->business->name ?? 'unknown',
                'total_amount' => $invoice->total_amount ?? 0,
                'service_charge' => $invoice->service_charge ?? 0,
                'payment_status' => $invoice->payment_status ?? 'unknown',
                'invoice_status' => $invoice->status ?? 'unknown'
            ]);

            // Generate PDF receipt
            Log::info("=== GENERATING PDF RECEIPT ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number
            ]);
            
            $pdfPath = $this->generateReceiptPDF($invoice);
            
            Log::info("=== PDF GENERATION RESULT ===", [
                'invoice_id' => $invoice->id,
                'pdf_path' => $pdfPath,
                'pdf_exists' => $pdfPath ? file_exists($pdfPath) : false,
                'pdf_size' => $pdfPath ? filesize($pdfPath) : 0
            ]);
            
            // Get KashTre super business (ID = 1)
            Log::info("=== FETCHING KASHTRE BUSINESS ===", [
                'invoice_id' => $invoice->id,
                'kashtre_business_id' => 1
            ]);
            
            $kashtreBusiness = Business::find(1);
            $chargeAmount = $invoice->service_charge; // Use the actual service charge from the invoice

            Log::info("=== KASHTRE BUSINESS FETCHED ===", [
                'invoice_id' => $invoice->id,
                'kashtre_business_found' => $kashtreBusiness ? 'yes' : 'no',
                'kashtre_business_name' => $kashtreBusiness->name ?? 'not found',
                'kashtre_business_email' => $kashtreBusiness->email ?? 'not found',
                'charge_amount' => $chargeAmount
            ]);

            // Send receipts to all parties
            Log::info("=== SENDING CLIENT RECEIPT ===", [
                'invoice_id' => $invoice->id,
                'client_email' => $invoice->client->email ?? 'no email',
                'pdf_attached' => 'no' // Client doesn't receive PDF
            ]);
            $this->sendClientReceipt($invoice, null); // No PDF for client
            
            Log::info("=== SENDING BUSINESS RECEIPT ===", [
                'invoice_id' => $invoice->id,
                'business_email' => $invoice->business->email ?? 'no email',
                'pdf_attached' => 'no' // Business doesn't receive PDF
            ]);
            $this->sendBusinessReceipt($invoice, null); // No PDF for business
            
            // Ensure business relationship is loaded for the invoice
            $invoice->load(['business', 'client']);
            
            Log::info("=== SENDING KASHTRE RECEIPT ===", [
                'invoice_id' => $invoice->id,
                'kashtre_email' => $kashtreBusiness && $kashtreBusiness->email ? $kashtreBusiness->email : config('mail.kashtre_email', 'admin@kashtre.com'),
                'pdf_attached' => 'yes', // Only Kashtre receives PDF
                'invoice_business_loaded' => $invoice->business ? 'yes' : 'no',
                'invoice_business_name' => $invoice->business->name ?? 'N/A',
                'invoice_business_email' => $invoice->business->email ?? 'N/A'
            ]);
            $this->sendKashTreReceipt($invoice, $chargeAmount, $pdfPath, $kashtreBusiness);

            Log::info("=== ELECTRONIC RECEIPTS SENT SUCCESSFULLY ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'pdf_generated' => $pdfPath ? 'yes' : 'no',
                'pdf_path' => $pdfPath
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("=== FAILED TO SEND ELECTRONIC RECEIPTS ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Generate PDF receipt for the invoice
     */
    private function generateReceiptPDF(Invoice $invoice)
    {
        try {
            Log::info("=== PDF GENERATION - STARTING ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'view_file' => 'invoices.print'
            ]);

            $invoice->loadMissing('business');

            $pdf = $this->documents->render(
                'invoices.print',
                [
                    'invoice' => $invoice,
                    'forPdf' => true,
                ],
                $invoice->business,
            );
            
            Log::info("=== PDF GENERATION - PDF OBJECT CREATED ===", [
                'invoice_id' => $invoice->id,
                'pdf_class' => get_class($pdf)
            ]);
            
            // Create filename
            $filename = 'receipt_' . $invoice->invoice_number . '_' . time() . '.pdf';
            $pdfPath = storage_path('app/receipts/' . $filename);
            
            Log::info("=== PDF GENERATION - FILENAME CREATED ===", [
                'invoice_id' => $invoice->id,
                'filename' => $filename,
                'pdf_path' => $pdfPath,
                'storage_path' => storage_path('app/receipts/'),
                'directory_exists' => file_exists(dirname($pdfPath))
            ]);
            
            // Ensure directory exists
            if (!file_exists(dirname($pdfPath))) {
                Log::info("=== PDF GENERATION - CREATING DIRECTORY ===", [
                    'invoice_id' => $invoice->id,
                    'directory' => dirname($pdfPath)
                ]);
                mkdir(dirname($pdfPath), 0755, true);
            }
            
            // Save PDF
            Log::info("=== PDF GENERATION - SAVING PDF ===", [
                'invoice_id' => $invoice->id,
                'pdf_path' => $pdfPath
            ]);
            
            $pdf->save($pdfPath);
            
            Log::info("=== PDF GENERATION - PDF SAVED SUCCESSFULLY ===", [
                'invoice_id' => $invoice->id,
                'pdf_path' => $pdfPath,
                'filename' => $filename,
                'file_exists' => file_exists($pdfPath),
                'file_size' => file_exists($pdfPath) ? filesize($pdfPath) : 0
            ]);
            
            return $pdfPath;

        } catch (\Exception $e) {
            Log::error("=== PDF GENERATION FAILED ===", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }

    /**
     * Send receipt to client
     */
    private function sendClientReceipt(Invoice $invoice, $pdfPath = null)
    {
        try {
            Log::info("=== CLIENT RECEIPT - CHECKING EMAIL ===", [
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'client_name' => $invoice->client->name ?? 'unknown',
                'client_email' => $invoice->client->email ?? 'no email',
                'pdf_path' => $pdfPath,
                'pdf_exists' => $pdfPath ? file_exists($pdfPath) : false
            ]);

            if (!$invoice->client->email) {
                Log::warning("=== CLIENT RECEIPT SKIPPED - NO EMAIL ===", [
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'client_name' => $invoice->client->name ?? 'unknown'
                ]);
                return;
            }

            Log::info("=== CLIENT RECEIPT - SENDING EMAIL ===", [
                'invoice_id' => $invoice->id,
                'client_email' => $invoice->client->email,
                'client_name' => $invoice->client->name,
                'mail_class' => 'ClientReceipt',
                'pdf_attached' => $pdfPath ? 'yes' : 'no'
            ]);

            Mail::to($invoice->client->email)->send(new ClientReceipt($invoice, $pdfPath));
            
            Log::info("=== CLIENT RECEIPT SENT SUCCESSFULLY ===", [
                'invoice_id' => $invoice->id,
                'client_email' => $invoice->client->email,
                'client_name' => $invoice->client->name,
                'pdf_attached' => $pdfPath ? 'yes' : 'no'
            ]);

        } catch (\Exception $e) {
            Log::error("=== CLIENT RECEIPT SENDING FAILED ===", [
                'invoice_id' => $invoice->id,
                'client_email' => $invoice->client->email ?? 'no email',
                'client_name' => $invoice->client->name ?? 'unknown',
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send receipt to business
     */
    private function sendBusinessReceipt(Invoice $invoice, $pdfPath = null)
    {
        try {
            Log::info("=== BUSINESS RECEIPT - CHECKING EMAIL ===", [
                'invoice_id' => $invoice->id,
                'business_id' => $invoice->business_id,
                'business_name' => $invoice->business->name ?? 'unknown',
                'business_email' => $invoice->business->email ?? 'no email',
                'pdf_path' => $pdfPath,
                'pdf_exists' => $pdfPath ? file_exists($pdfPath) : false
            ]);

            if (!$invoice->business->email) {
                Log::warning("=== BUSINESS RECEIPT SKIPPED - NO EMAIL ===", [
                    'invoice_id' => $invoice->id,
                    'business_id' => $invoice->business_id,
                    'business_name' => $invoice->business->name ?? 'unknown'
                ]);
                return;
            }

            Log::info("=== BUSINESS RECEIPT - SENDING EMAIL ===", [
                'invoice_id' => $invoice->id,
                'business_email' => $invoice->business->email,
                'business_name' => $invoice->business->name,
                'mail_class' => 'BusinessReceipt',
                'pdf_attached' => $pdfPath ? 'yes' : 'no'
            ]);

            Mail::to($invoice->business->email)->send(new BusinessReceipt($invoice, $pdfPath));
            
            Log::info("=== BUSINESS RECEIPT SENT SUCCESSFULLY ===", [
                'invoice_id' => $invoice->id,
                'business_email' => $invoice->business->email,
                'business_name' => $invoice->business->name,
                'pdf_attached' => $pdfPath ? 'yes' : 'no'
            ]);

        } catch (\Exception $e) {
            Log::error("=== BUSINESS RECEIPT SENDING FAILED ===", [
                'invoice_id' => $invoice->id,
                'business_email' => $invoice->business->email ?? 'no email',
                'business_name' => $invoice->business->name ?? 'unknown',
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send receipt to KashTre
     */
    private function sendKashTreReceipt(Invoice $invoice, $chargeAmount, $pdfPath = null, $kashtreBusiness = null)
    {
        try {
            // Use KashTre business email if available, otherwise use a default
            $kashtreEmail = $kashtreBusiness && $kashtreBusiness->email ? 
                $kashtreBusiness->email : 
                config('mail.kashtre_email', 'admin@kashtre.com');

            Log::info("=== KASHTRE RECEIPT - CHECKING EMAIL ===", [
                'invoice_id' => $invoice->id,
                'kashtre_business_found' => $kashtreBusiness ? 'yes' : 'no',
                'kashtre_business_email' => $kashtreBusiness->email ?? 'no email',
                'config_kashtre_email' => config('mail.kashtre_email'),
                'final_kashtre_email' => $kashtreEmail,
                'charge_amount' => $chargeAmount,
                'pdf_path' => $pdfPath,
                'pdf_exists' => $pdfPath ? file_exists($pdfPath) : false
            ]);

            Log::info("=== KASHTRE RECEIPT - SENDING EMAIL ===", [
                'invoice_id' => $invoice->id,
                'kashtre_email' => $kashtreEmail,
                'charge_amount' => $chargeAmount,
                'mail_class' => 'KashTreReceipt',
                'pdf_attached' => $pdfPath ? 'yes' : 'no'
            ]);

            Mail::to($kashtreEmail)->send(new KashTreReceipt($invoice, $chargeAmount, $pdfPath));
            
            Log::info("=== KASHTRE RECEIPT SENT SUCCESSFULLY ===", [
                'invoice_id' => $invoice->id,
                'kashtre_email' => $kashtreEmail,
                'charge_amount' => $chargeAmount,
                'pdf_attached' => $pdfPath ? 'yes' : 'no'
            ]);

        } catch (\Exception $e) {
            Log::error("=== KASHTRE RECEIPT SENDING FAILED ===", [
                'invoice_id' => $invoice->id,
                'kashtre_email' => $kashtreEmail ?? 'unknown',
                'charge_amount' => $chargeAmount,
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

}
