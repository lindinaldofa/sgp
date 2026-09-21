<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Console\Command;

/**
 * One-off data fix: some contacts ended up with lgpd_consent=false due to a
 * since-fixed default-value bug during CSV import. Scoped to a single tag
 * (the one chosen for that import run) rather than every false row, since a
 * contact from a public form may have lgpd_consent=false because the person
 * genuinely unchecked consent — that must never be silently overwritten.
 */
class FixLgpdConsentByTag extends Command
{
    protected $signature = 'contacts:fix-lgpd-consent
        {tag : Nome exato da tag usada na importação}
        {--dry-run : Apenas mostra quantos contatos seriam afetados, sem alterar nada}';

    protected $description = 'Corrige lgpd_consent=false para true nos contatos que têm uma tag específica';

    public function handle(): int
    {
        $tagName = (string) $this->argument('tag');
        $tag = Tag::where('name', $tagName)->first();

        if (! $tag) {
            $this->error("Tag \"{$tagName}\" não encontrada.");

            return self::FAILURE;
        }

        $query = Contact::whereHas('tags', fn ($q) => $q->where('tags.id', $tag->id))
            ->where('lgpd_consent', false);

        $count = $query->count();
        $this->info("Contatos com a tag \"{$tagName}\" e lgpd_consent=false: {$count}");

        if ($count === 0) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->comment('Modo dry-run: nenhuma alteração foi feita.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Confirma a atualização de {$count} contato(s) para lgpd_consent=true?")) {
            $this->comment('Cancelado.');

            return self::SUCCESS;
        }

        $updated = $query->update([
            'lgpd_consent' => true,
            'lgpd_consent_at' => now(),
        ]);

        $this->info("{$updated} contato(s) atualizado(s).");

        return self::SUCCESS;
    }
}
