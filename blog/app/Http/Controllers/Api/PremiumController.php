<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PageRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Premium as the bot sees it. The bot sells the monthly subscription in
 * Telegram Stars; it asks here whether a Telegram account may buy it (on
 * /premium and before Telegram charges), and reports every payment, the
 * first one and each renewal, so premium_until follows the subscription.
 */
class PremiumController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telegram_user_id' => ['required', 'integer', 'min:1'],
        ]);

        $author = PageRequest::authorFor($data['telegram_user_id']);

        if ($author === null) {
            return response()->json(['message' => 'No author has this Telegram account.'], 404);
        }

        return response()->json($this->state($author));
    }

    public function payment(Request $request): JsonResponse
    {
        // The fields of Telegram's SuccessfulPayment, as the bot received them.
        $data = $request->validate([
            'telegram_user_id' => ['required', 'integer', 'min:1'],
            'telegram_payment_charge_id' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'in:XTR'],
            'total_amount' => ['required', 'integer', 'min:1'],
            'invoice_payload' => ['nullable', 'string', 'max:128'],
            'subscription_expiration_date' => ['required', 'integer', 'min:1'],
            'is_recurring' => ['boolean'],
            'is_first_recurring' => ['boolean'],
        ]);

        $author = PageRequest::authorFor($data['telegram_user_id']);

        // The bot checks before Telegram charges, so this means the author
        // was removed in between. The bot refunds; nothing is recorded.
        if ($author === null) {
            return response()->json(['message' => 'No author has this Telegram account.'], 404);
        }

        $until = Carbon::createFromTimestamp($data['subscription_expiration_date']);

        $payment = DB::transaction(function () use ($author, $data, $until): Payment {
            // A blocked author's subscription still renews, and the money is
            // still taken, so their payment is kept too; the admin can refund.
            $payment = Payment::firstOrCreate(
                [
                    'provider' => Payment::PROVIDER_STARS,
                    'provider_charge_id' => $data['telegram_payment_charge_id'],
                ],
                [
                    'user_id' => $author->id,
                    'amount' => $data['total_amount'],
                    'currency' => $data['currency'],
                    'telegram_user_id' => $data['telegram_user_id'],
                    'is_recurring' => (bool) ($data['is_recurring'] ?? false),
                    'premium_until' => $until,
                    'payload' => $data,
                ],
            );

            if ($payment->wasRecentlyCreated) {
                $author->extendPremiumTo($until);
            }

            return $payment;
        });

        return response()->json(
            $this->state($author->refresh()) + ['payment_id' => $payment->id],
            $payment->wasRecentlyCreated ? 201 : 200,
        );
    }

    /** @return array{author: string, premium: bool, premium_until: ?string, blocked: bool} */
    private function state(User $author): array
    {
        return [
            'author' => $author->username,
            'premium' => $author->isPremium(),
            'premium_until' => $author->premium_until?->toAtomString(),
            'blocked' => $author->isBlocked(),
        ];
    }
}
