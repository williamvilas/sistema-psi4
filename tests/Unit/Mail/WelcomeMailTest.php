<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Listeners\SendWelcomeMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WelcomeMailTest extends TestCase
{
    public function testItUsesFirstNameAndWhatsappFromConfig(): void
    {
        config(['info.whatsapp' => '11 99999-0000']);

        $html = (new WelcomeMail('Maria Souza'))->render();

        $this->assertStringContainsString('Olá, Maria!', $html);
        $this->assertStringContainsString(route('whatsapp.contact'), $html);
        $this->assertStringContainsString('pessoas de verdade', $html);
        $this->assertStringContainsString(config('info.support.name'), $html);
    }

    public function testItOmitsNameWhenItLooksLikeEmailPrefix(): void
    {
        $html = (new WelcomeMail('joao.silva'))->render();

        $this->assertStringContainsString('Olá!', $html);
        $this->assertStringNotContainsString('joao', $html);
    }

    public function testItSetsReplyTo(): void
    {
        $mail = new WelcomeMail('Maria');

        $this->assertTrue($mail->hasReplyTo(config('info.support.email')));
        $this->assertSame(__('welcome.mail_subject'), $mail->envelope()->subject);
    }

    public function testListenerSendsMailOnRegistered(): void
    {
        Mail::fake();
        $user = new User(['name' => 'Maria Souza', 'email' => 'maria@example.com']);

        (new SendWelcomeMail())->handle(new Registered($user));

        Mail::assertQueued(WelcomeMail::class, fn ($mail) => $mail->hasTo('maria@example.com'));
    }

    public function testListenerSwallowsMailFailure(): void
    {
        Queue::fake();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $user = new User(['name' => 'Maria', 'email' => 'maria@example.com']);

        (new SendWelcomeMail())->handle(new Registered($user));

        $this->addToAssertionCount(1);
    }
}
