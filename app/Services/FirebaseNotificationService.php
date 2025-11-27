<?php

namespace App\Services;

use App\Models\Aspirante;
use App\Models\DeviceToken;
use function collect;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

class FirebaseNotificationService
{
    public function __construct(private Messaging $messaging)
    {
    }

    /**
     * @param string[] $tokens
     * @return array{tokens:int,success:int,failure:int}
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if (empty($tokens)) {
            return ['tokens' => 0, 'success' => 0, 'failure' => 0];
        }

        $message = CloudMessage::new()
            ->withNotification([
                'title' => $title,
                'body' => $body,
            ])
            ->withData($this->buildPayload($data));

        $report = $this->messaging->sendMulticast($message, $tokens);

        foreach ($report->failures()->getItems() as $failure) {
            $invalidToken = $failure->target()->value();
            DeviceToken::where('fcm_token', $invalidToken)->delete();
        }

        return [
            'tokens' => count($tokens),
            'success' => $report->successes()->count(),
            'failure' => $report->failures()->count(),
        ];
    }

    public function notifyAspirante(Aspirante $aspirante, string $title, string $body, array $data = []): array
    {
        $tokens = $aspirante->deviceTokens()->pluck('fcm_token')->all();
        $data = array_merge([
            'id_aspirantes' => (string) $aspirante->id_aspirantes,
        ], $data);

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    private function buildPayload(array $data): array
    {
        $payload = array_merge([
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ], $data);

        return collect($payload)
            ->map(static function ($value) {
                if (is_scalar($value) || $value === null) {
                    return (string) ($value ?? '');
                }

                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            })
            ->all();
    }
}
