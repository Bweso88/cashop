<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Enums\ActorType;
use App\Support\Http\CorrelationId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Journal d'audit en ajout seul, chaîné par hachage (docs/SECURITY.md §8).
 *
 * hash = SHA-256(previous_hash | contenu canonique). Modifier ou supprimer une ligne casse la
 * chaîne, ce que verifyChain() détecte. Un verrou consultatif PostgreSQL sérialise les écritures
 * pour que deux requêtes concurrentes ne partent pas du même previous_hash.
 */
class AuditLogger
{
    private const LOCK_KEY = 7_406_011; // identifiant arbitraire du verrou consultatif

    /** Clés jamais journalisées en clair. */
    private const SENSITIVE = ['password', 'pin', 'transaction_pin_hash', 'code', 'otp', 'token', 'secret', 'mfa_secret', 'signature', 'card_number', 'pan'];

    public function __construct(private readonly ?string $ip = null, private readonly ?string $userAgent = null) {}

    public function log(
        string $action,
        ActorType $actorType,
        ?string $actorId = null,
        ?Model $subject = null,
        array $changes = [],
    ): void {
        DB::transaction(function () use ($action, $actorType, $actorId, $subject, $changes) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [self::LOCK_KEY]);

            $previous = DB::table('audit_logs')->orderByDesc('id')->value('hash');
            $row = [
                'actor_type' => $actorType->value,
                'actor_id' => $actorId,
                'action' => $action,
                'subject_type' => $subject ? $subject->getMorphClass() : null,
                'subject_id' => $subject?->getKey(),
                'ip' => $this->ip,
                'user_agent' => $this->userAgent ? mb_substr($this->userAgent, 0, 500) : null,
                'correlation_id' => CorrelationId::get(),
                'changes' => $changes === [] ? null : json_encode(self::mask($changes)),
                'created_at' => now()->toImmutable()->format('Y-m-d H:i:s.uP'),
                'previous_hash' => $previous,
            ];
            $row['hash'] = self::hash($row);

            DB::table('audit_logs')->insert($row);
        });
    }

    /** Vérifie l'intégrité de toute la chaîne ; renvoie l'id de la première ligne invalide ou null. */
    public static function verifyChain(): ?int
    {
        $previous = null;
        foreach (DB::table('audit_logs')->orderBy('id')->cursor() as $row) {
            $data = (array) $row;
            if ($data['previous_hash'] !== $previous || ! hash_equals($data['hash'], self::hash($data))) {
                return (int) $data['id'];
            }
            $previous = $data['hash'];
        }

        return null;
    }

    private static function hash(array $row): string
    {
        // jsonb normalise espaces et ordre des clés : on hache une forme canonique.
        $row['changes'] = isset($row['changes']) ? self::canonicalJson(json_decode($row['changes'], true)) : '';
        $fields = ['actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'ip', 'correlation_id', 'changes', 'previous_hash'];
        $canonical = implode('|', array_map(fn ($f) => (string) ($row[$f] ?? ''), $fields));

        return hash('sha256', $canonical);
    }

    private static function canonicalJson(mixed $value): string
    {
        $sort = function (mixed $v) use (&$sort): mixed {
            if (! is_array($v)) {
                return $v;
            }
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map($sort, $v);
        };

        return json_encode($sort($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::mask($value);
            } elseif (in_array(strtolower((string) $key), self::SENSITIVE, true)) {
                $data[$key] = '[MASQUÉ]';
            }
        }

        return $data;
    }
}
