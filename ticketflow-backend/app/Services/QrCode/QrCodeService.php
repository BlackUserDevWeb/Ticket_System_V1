<?php

namespace App\Services\QrCode;

use App\Models\Ticket;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Illuminate\Support\Facades\Storage;

/**
 * QrCodeService — génération des QR codes de tickets (endroid/qr-code v5).
 *
 * Le QR encode TICKFLOW1.<uuid>.<hmac> : la partie HMAC est calculée avec APP_KEY,
 * donc impossible à forged. La PNG est stockée sur disque (storage/app/public/tickets)
 * et renvoyée par l'API via un lien signé temporaires ; le PDF "passe" téléchargeable
 * côté acheteur embarque la même image.
 */
class QrCodeService
{
    /** Calcule la signature HMAC tronquée intégrée au QR (et vérifiée au scan). */
    public function signatureFor(string $ticketUuid): string
    {
        return substr(
            hash_hmac('sha256', config('ticketflow.qr.payload_prefix') . '.' . $ticketUuid, config('app.key')),
            0,
            config('ticketflow.qr.signature_length')
        );
    }

    /** Vérifie un payload complet scanné (mode online du scanner). */
    public function verifyPayload(string $payload): ?string
    {
        $parts = explode('.', $payload);
        if (count($parts) !== 3 || $parts[0] !== config('ticketflow.qr.payload_prefix')) {
            return null;
        }
        [, $uuid, $signature] = $parts;
        return hash_equals($this->signatureFor($uuid), $signature) ? $uuid : null;
    }

    /** Génère le PNG du ticket et retourne son chemin relatif storage. */
    public function generateForTicket(Ticket $ticket): string
    {
        $path = "tickets/{$ticket->code}.png";

        $result = Builder::create()
            ->writer(new \Endroid\QrCode\Writer\PngWriter())
            ->data($ticket->qrPayload())
            ->encoding(new Encoding('UTF-8'))
            // Medium : bon compromis lisibilité mobile / densité (écrans 375px).
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size(480)
            ->margin(12)
            ->labelText(null)
            ->build();

        Storage::disk('public')->put($path, $result->getString());

        return 'storage/' . $path;
    }
}
