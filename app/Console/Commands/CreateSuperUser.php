<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateSuperUser extends Command
{
    protected $signature = 'madematik:create-super-user';
    protected $description = 'Yalnızca bu ana site için güvenli, ayrı bir Super User hesabı oluşturur.';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) { $this->error('Bu komut etkileşimli terminal gerektirir. Parolayı komut satırına yazmayın.'); return self::FAILURE; }
        $name = $this->ask('Ad soyad');
        $email = Str::lower((string) $this->ask('Yeni ve ayrı yönetici e-posta adresi'));
        $password = $this->secret('Parola (en az 12 karakter, büyük/küçük harf, rakam ve sembol)');
        $confirmation = $this->secret('Parolayı tekrar girin');
        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], ['name' => ['required', 'string', 'min:2', 'max:100'], 'email' => ['required', 'email', 'max:254', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::defaults()]]);
        if ($validator->fails()) { foreach ($validator->errors()->all() as $error) { $this->error($error); } return self::FAILURE; }
        $this->line('Bu komut başka uygulamalar veya aWa hesaplarında işlem yapmaz.');
        if (! $this->confirm('Bu yeni hesabı ana sitenin Super User hesabı olarak oluşturulsun mu?', false)) { return self::FAILURE; }
        $user = new User();
        $user->forceFill(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => 'super_user', 'locale' => 'tr'])->save();
        $user->notificationPreference()->create(['comment_mail' => false, 'reply_mail' => false, 'project_mail' => false, 'site_notifications' => true]);
        ActivityLog::record('user.super_user_created', $user, ['source' => 'console']);
        $this->info('Super User oluşturuldu. İlk girişte e-posta doğrulamasını tamamlayın; ardından /admin adresini açın.');
        return self::SUCCESS;
    }
}
