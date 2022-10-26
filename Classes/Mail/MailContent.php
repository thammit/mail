<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Mail;

final class MailContent
{
    public string $subject;
    public string $text;
    public string $html;

    public function __construct(string $subject = '', string $text = '', string $html = '')
    {
        $this->subject = $subject;
        $this->text = $text;
        $this->html = $html;
    }
}
