<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\SharedTime;
class Transaction extends Model
{
    use HasFactory, SoftDeletes;



     protected $fillable = [
        'business_id',
        'branch_id',
        'client_id',
        'invoice_id',
        'amount',
        'reference',
        'external_reference',
        'description',
        'status',
        'payment_status',
        'type',
        'origin',
        'phone_number',
        'provider',
        'service',
        'date',
        'currency',
        'names',
        'email',
        'ip_address',
        'user_agent',
        'method',
        'transaction_for'
     ];



     //relationships
     public function business()
     {
         return $this->belongsTo(Business::class);
     }

     public function branch()
     {
         return $this->belongsTo(Branch::class);
     }

     public function client()
     {
         return $this->belongsTo(Client::class);
     }

     public function invoice()
     {
         return $this->belongsTo(Invoice::class);
     }

     protected static function booted()
    {
        static::creating(function ($transaction) {
            $transaction->uuid = (string) Str::uuid();

            if ($transaction->business_id) {
                try {
                    $transaction->date = SharedTime::businessToday(
                        (string) $transaction->business_id,
                        $transaction->branch_id ? (string) $transaction->branch_id : null,
                    );
                } catch (\Throwable) {
                    // Keep any date already supplied by the caller.
                }
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
