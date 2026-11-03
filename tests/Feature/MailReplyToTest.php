<?php

namespace Tests\Feature;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MailReplyToTest extends TestCase
{
    public function test_otomatik_e_postalarin_yaniti_ayarli_adrese_gider(): void
    {
        config([
            'mail.default' => 'array',
            'mail.from' => ['address' => 'info@example.com', 'name' => 'mADEMatik Solutions'],
            'mail.reply_to' => ['address' => 'yanit@example.com', 'name' => 'mADEMatik Solutions'],
        ]);
        // TestCase Mail::fake() kullanır; ayarı gerçek göndericide denetle.
        $manager = new \Illuminate\Mail\MailManager($this->app);
        $manager->purge('array');

        $replyTo = null;
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$replyTo) {
            $replyTo = $event->message->getReplyTo()[0]->getAddress();
        });

        $manager->mailer('array')->raw('deneme', fn ($m) => $m->to('kisi@example.com')->subject('Deneme'));

        $this->assertSame('yanit@example.com', $replyTo);
    }
}
