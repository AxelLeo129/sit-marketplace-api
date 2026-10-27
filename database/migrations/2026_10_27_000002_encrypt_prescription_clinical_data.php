<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const COLUMNS = ['symptoms', 'diagnosis', 'observations'];

    /**
     * Cifra los síntomas, diagnósticos y observaciones que ya estaban guardados
     * en texto plano. Las recetas nuevas se cifran solas (cast "encrypted").
     */
    public function up(): void
    {
        $this->transform(fn (string $value) => $this->isEncrypted($value) ? $value : Crypt::encryptString($value));
    }

    /**
     * Los vuelve a dejar en texto plano.
     */
    public function down(): void
    {
        $this->transform(fn (string $value) => $this->isEncrypted($value) ? Crypt::decryptString($value) : $value);
    }

    private function transform(Closure $change): void
    {
        DB::table('prescriptions')->orderBy('id')->each(function (object $row) use ($change) {
            $values = [];
            foreach (self::COLUMNS as $column) {
                if ($row->{$column} !== null && $row->{$column} !== '') {
                    $values[$column] = $change($row->{$column});
                }
            }

            if ($values) {
                DB::table('prescriptions')->where('id', $row->id)->update($values);
            }
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
