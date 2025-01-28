<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Knp\Snappy\Pdf;
use PhpImap\Imap;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use PhpImap\Mailbox;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:sendPdf',
    description: 'Read an email and resend the content as a PDF',
)]
class sendPdf extends Command
{
    private $pdf;
    private $sent = 0;

    public function __construct(Pdf $pdf)
    {
        parent::__construct();
        $this->pdf = $pdf;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filesystem = new Filesystem();

        $mailbox = new Mailbox(
            '{imap.gmail.com:993/imap/ssl}INBOX',
            $_ENV['MAILBOX_EMAIL'], 
            $_ENV['MAILBOX_PASSWORD'], 
            null 
        );
        $allUnseenEmails = Imap::search($mailbox->getImapStream(), 'UNSEEN');
        $unseenEmails = Imap::search($mailbox->getImapStream(),'UNSEEN FROM "@aircury.com"');

        $uniqueEmails = array_merge(
            array_diff($allUnseenEmails, $unseenEmails),
            array_diff($unseenEmails, $allUnseenEmails)
        );

        for ($i = 0; $i < count($uniqueEmails); $i++){

            Imap::delete($mailbox->getImapStream(),$uniqueEmails[$i]);

        }

        $unseenEmails = Imap::search($mailbox->getImapStream(),'UNSEEN FROM "@aircury.com"');

        while (count($unseenEmails) > 0){

            $mail = $unseenEmails[0];

            $body = Imap::fetchbody($mailbox->getImapStream(),$mail,2);  
            
            $header = Imap::fetchheader($mailbox->getImapStream(),$mail,0);

            $AttachmentLinePos = strpos($header, 'Content-Type:');

            if ($AttachmentLinePos !== false){

                $AttachmentLine = substr($header, $AttachmentLinePos, strpos($header, ";", $AttachmentLinePos) - $AttachmentLinePos);

                $contentType = trim(substr($AttachmentLine, strlen('Content-Type:')));
                
                if($contentType !== "multipart/mixed"){

                    $subjectLinePos = strpos($header, 'Subject:');

                    if ($subjectLinePos !== false) {

                        $subjectLine = substr($header, $subjectLinePos, strpos($header, "\n", $subjectLinePos) - $subjectLinePos);

                        $subject = trim(substr($subjectLine, strlen('Subject:'))); 
                        
                        $fromLinePos = strpos($header, 'From:');
            
                        if ($fromLinePos !== false) {

                            $fromLine = substr($header, $fromLinePos, strpos($header, "\n", $fromLinePos) - $fromLinePos);

                            $startPos = strpos($fromLine, '<');
                            $endPos = strpos($fromLine, '>');

                            if ($startPos !== false && $endPos !== false) {

                                $to = substr($fromLine, $startPos + 1, $endPos - $startPos - 1);

                                $filesystem->dumpFile('templates/pdf.html.twig',$body);

                                $pdf = $this->pdf->getOutputFromHtml(file_get_contents('templates/pdf.html.twig'));

                                $transport = Transport::fromDsn($_ENV['MAILER_DSN']);

                                $mailer = new Mailer($transport);
                                
                                $email = (new Email())
                                ->from($_ENV['MAILBOX_EMAIL'])
                                ->to($to)
                                ->subject('PDF')
                                ->attach($pdf,sprintf($subject.'.pdf'));

                                try{

                                    $mailer->send($email);
                                    $this->sent++;

                                }catch(\Throwable $th){

                                    echo $th->getMessage();

                                } 
                            } else {
                                echo "Invalid email. \n";
                                Imap::delete($mailbox->getImapStream(),$mail);
                            }
                        } else {
                            echo "Couldn't find email address from header. \n";
                            Imap::delete($mailbox->getImapStream(),$mail);
                        }
                    } else {
                        echo "Couldn't find subject. \n";
                        Imap::delete($mailbox->getImapStream(),$mail);
                    }
                }else{
                    echo "Can't convert to PDF if there is an attachment. \n";
                    Imap::delete($mailbox->getImapStream(),$mail);
                }
            }
            $unseenEmails = Imap::search($mailbox->getImapStream(),'UNSEEN FROM "@aircury.com"');                    
        }
        $io->success('Sent a total of '.$this->sent.' PDF');
        return Command::SUCCESS;  
    }
}