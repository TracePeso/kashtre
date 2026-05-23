<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\ContractorProfile;
use App\Models\ContractorBalanceHistory;
use App\Models\BusinessBalanceHistory;

class Dashboard extends Component
{
    public $business;
    public $currentBranch;
    public $balance;
    public $lastUpdate;
    public $isContractor = false;
    public $contractorProfile;
    public $assignedServicePoints;

    public function mount()
    {
        $user = Auth::user();

        // Check if user is a contractor
        $this->contractorProfile = ContractorProfile::where('user_id', $user->id)->first();
        $this->isContractor = $this->contractorProfile !== null;

        if ($this->isContractor) {
            // Contractor dashboard - calculate balance from contractor_balance_histories (source of truth)
            $balanceHistory = \App\Models\ContractorBalanceHistory::where('contractor_profile_id', $this->contractorProfile->id)
                ->selectRaw('SUM(CASE WHEN type = "credit" THEN amount ELSE -amount END) as calculated_balance')
                ->first();
            
            $this->balance = $balanceHistory ? $balanceHistory->calculated_balance : 0;
            
            // Get assigned service points for this contractor from user's service_points field
            if ($user->service_points && is_array($user->service_points) && count($user->service_points) > 0) {
                $this->assignedServicePoints = \App\Models\ServicePoint::whereIn('id', $user->service_points)->get();
            } else {
                $this->assignedServicePoints = collect([]);
            }
        } else {
            // Regular staff dashboard
            $this->business = $user->business;
            $this->currentBranch = $user->current_branch;

            $this->balance = (float) (
                BusinessBalanceHistory::where('business_id', $this->business->id)
                    ->selectRaw("COALESCE(SUM(CASE WHEN type = 'credit' THEN amount WHEN type = 'debit' THEN -amount ELSE 0 END), 0) as calculated_balance")
                    ->value('calculated_balance') ?? 0
            );
        }

        $this->lastUpdate = now()->format('H:i:s');
    }

    public function render()
    {
        if ($this->isContractor) {
            return view('livewire.contractor-dashboard');
        }
        
        return view('livewire.dashboard');
    }
}
