<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\PasswordCredential;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Establish or replace a user's password credential.
 *
 * The only writer of `password_credentials.password_hash` outside the reset
 * flow. Always goes through the Hash service; a raw password never reaches the
 * database, a log, or an exception message.
 */
final class SetPassword
{
    public function execute(User $user, string $plainPassword): PasswordCredential
    {
        return DB::transaction(function () use ($user, $plainPassword): PasswordCredential {
            $credential = PasswordCredential::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first() ?? new PasswordCredential();

            $credential->forceFill([
                'user_id' => $user->getKey(),
                'password_hash' => Hash::make($plainPassword),
                'password_changed_at' => now(),
                'created_at' => $credential->created_at ?? now(),
                'updated_at' => now(),
            ])->save();

            return $credential;
        });
    }
}
