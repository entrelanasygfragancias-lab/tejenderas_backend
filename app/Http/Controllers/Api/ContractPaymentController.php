<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContractPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractPaymentController extends Controller
{
    /**
     * Display a listing of contract payments.
     */
    public function index()
    {
        $payments = ContractPayment::with('contract')->latest()->get();
        return response()->json($payments);
    }

    /**
     * Store a newly created contract payment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $payment = ContractPayment::create($validated);

            // Update contract status if needed
            $contract = $payment->contract;
            $totalPaid = $contract->payments()->sum('amount');
            
            if ($totalPaid >= $contract->total) {
                $contract->update(['status' => 'paid']);
            } elseif ($totalPaid > 0) {
                $contract->update(['status' => 'partial']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Abono registrado exitosamente',
                'payment' => $payment->load('contract')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar el abono',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified contract payment.
     */
    public function show(ContractPayment $contractPayment)
    {
        return response()->json($contractPayment->load('contract'));
    }

    /**
     * Update the specified contract payment.
     */
    public function update(Request $request, ContractPayment $contractPayment)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $contractPayment->update($validated);

            // Update contract status if needed
            $contract = $contractPayment->contract;
            $totalPaid = $contract->payments()->sum('amount');
            
            if ($totalPaid >= $contract->total) {
                $contract->update(['status' => 'paid']);
            } elseif ($totalPaid > 0) {
                $contract->update(['status' => 'partial']);
            } else {
                $contract->update(['status' => 'pending']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Abono actualizado exitosamente',
                'payment' => $contractPayment->load('contract')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar el abono',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified contract payment from storage.
     */
    public function destroy(ContractPayment $contractPayment)
    {
        try {
            DB::beginTransaction();

            // Get contract before deletion
            $contract = $contractPayment->contract;

            // Delete the payment
            $contractPayment->delete();

            // Update contract status if needed
            $totalPaid = $contract->payments()->sum('amount');
            
            if ($totalPaid >= $contract->total) {
                $contract->update(['status' => 'paid']);
            } elseif ($totalPaid > 0) {
                $contract->update(['status' => 'partial']);
            } else {
                $contract->update(['status' => 'pending']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Abono eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar el abono',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
