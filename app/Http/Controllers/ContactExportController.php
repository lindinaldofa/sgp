<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactExportController extends Controller
{
    public function index(Request $request): StreamedResponse
    {
        $query = Contact::query();

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($city = $request->input('city')) {
            $query->where('city', 'like', "%{$city}%");
        }

        if ($state = $request->input('state')) {
            $query->where('state', $state);
        }

        if ($zone = $request->input('zone')) {
            $query->where('electoral_zone', $zone);
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Failed to open output stream');
            }

            // BOM for Excel UTF-8 compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID', 'ULID', 'Nome', 'CPF', 'Data de Nascimento',
                'E-mail', 'Telefone', 'WhatsApp', 'Tipo', 'Gênero',
                'Score de Engajamento', 'Cidade', 'Estado', 'Zona Eleitoral',
                'LGPD Consentimento', 'Criado em',
            ]);

            $query->chunk(500, function ($contacts) use ($handle) {
                foreach ($contacts as $contact) {
                    fputcsv($handle, [
                        $contact->id,
                        $contact->ulid,
                        $contact->name,
                        $contact->cpf,
                        $contact->birth_date?->toDateString(),
                        $contact->email,
                        $contact->phone,
                        $contact->whatsapp,
                        $contact->type?->value,
                        $contact->gender?->value,
                        $contact->engagement_score,
                        $contact->city,
                        $contact->state,
                        $contact->electoral_zone,
                        $contact->lgpd_consent ? 'Sim' : 'Não',
                        $contact->created_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, 'contacts_'.now()->format('Y-m-d_H-i-s').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
