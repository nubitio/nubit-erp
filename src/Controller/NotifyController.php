<?php

namespace App\Controller;

use App\Models\NotifyModel;
use App\Models\OptionsModel;
use MessageBird\Client;
use MessageBird\Objects\Conversation\Content;
use MessageBird\Objects\Conversation\SendMessage;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Template;

class NotifyController extends BaseController
{
    protected NotifyModel $model;
    protected Client $bird;
    protected OptionsModel $options;

    public function __construct()
    {
        parent::__construct();
        $this->model = new NotifyModel($this->db);
        $this->bird = new Client($this->app->get('ACCESS_KEY'));
        $this->options = new OptionsModel();
    }

    public function beforeroute()
    {
        //avoid redirect to login
    }

    public function sendMessage($recipient, $message)
    {
        $content = new Content();
        $content->text = $message;

        $sendMessage = new SendMessage();
        $sendMessage->from = $this->app->get('CHANNEL_ID');
        $sendMessage->to = $recipient;
        $sendMessage->content = $content;
        $sendMessage->type = 'text';
        $sendMessage->reportUrl = $this->app->get('REPORT_URL');

        try {
            $sendResult = $this->bird->conversationSend->send($sendMessage);
            return ["success" => true, "data" => $sendResult, "message" => "Mensaje enviado"];
        } catch (\Exception $e) {
            return ["success" => false, "data" => null, "message" => $e->getMessage()];
        }
    }

    public function sendEmail($data)
    {
        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->CharSet = 'UTF-8';
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = 'mail.jgm90.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'noreply@compuexpress.pe';
            $mail->Password = 'Admin.3000';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('noreply@compuexpress.pe', 'CompuExpress');
            foreach ($data['recipients'] as $recipient) {
                $mail->addAddress($recipient);
            }
            //$mail->addBCC('johangm90@gmail.com');

            // Content
            $mail->isHTML(true);
            $mail->Subject = $data['subject'];
            $mail->Body = $data['body'];
            $mail->AltBody = $data['altbody'];

            $mail->send();
            return ["success" => true, "data" => null, "message" => "Mensaje enviado"];
        } catch (Exception $e) {
            return ["success" => false, "data" => null, "message" => "Mailer Error: {$mail->ErrorInfo}"];
        }
    }

    public function lastSync()
    {
        $info = $this->model->lastSync();

        $message = [];
        $message['subject'] = '[RBCHACHANI] Notificación de sincronización - ' . date('d/m/Y');
        $message['recipients'] = explode(',', $this->options->getByName('app_alert_recipients'));

        $template = new Template();

        $this->app->set('logo', 'https://src.compuexpress.pe/src/assets/images/logo.png');
        $this->app->set('title', 'Notificación de sincronización');
        $this->app->set('subtitle', 'Hola, a continuación la fecha y hora de la última sincronización por local');
        $this->app->set('data', $info);

        $body = $template->render('templates/alert.html');

        $message['body'] = $body;
        $message['altbody'] = 'Notificación de sincronización';

        $this->json($this->sendEmail($message));
    }

    public function syncStatus()
    {

        $yesterday = date('Y-m-d', strtotime("-1 days"));
        $this->model->fill_comprobantes();
        $data = $this->model->get_syncstatus($yesterday);

        $message = [];
        $message['recipients'] = ['asist.contabilidadjpaqp@gmail.com'];
        $message['subject'] = '[RBCHACHANI] Notificación de estado de sincronzación - ' . date('d/m/Y');

        $body = "";
        $body .= "<b>Estado de sincronización a la fecha: {$yesterday}</b>";
        $body .= '<table cellspacing="0" cellpadding="0" border="1">';
        $body .= '<tr>';
        $body .= '<td><b>Local</b></td>';
        $body .= '<td><b>Serie</b></td>';
        $body .= '<td><b>Desde</b></td>';
        $body .= '<td><b>Hasta</b></td>';
        $body .= '<td><b>Registros</b></td>';
        $body .= '<td><b>H.Ultimo</b></td>';
        $body .= '</tr>';
        for ($i = 0; $i < count($data); $i++) {
            $body .= '<tr>';
            $body .= '<td>' . $data[$i]['local'] . '</td>';
            $body .= '<td>' . $data[$i]['serie'] . '</td>';
            $body .= '<td>' . $data[$i]['desde'] . '</td>';
            $body .= '<td>' . $data[$i]['hasta'] . '</td>';
            $body .= '<td>' . $data[$i]['comprobantes'] . '</td>';
            $body .= '<td>' . $data[$i]['hora_ultimo'] . '</td>';
            $body .= '</tr>';
        }
        $body .= '</table>';
        $message['body'] = $body;
        $message['altbody'] = 'Notificación de estado de sincronización';

        $this->json($this->sendEmail($message));
    }

    public function invoiceAlert()
    {
        //$info = $this->model->lastSync();
        $recipients = explode(',', $this->options->getByName('app_wsp_recipients'));
        $message = '[RBCHACHANI] Alerta de comrobantes rechazados por SUNAT';

        $result = [];
        foreach ($recipients as $recipient) {
            array_push($result, $this->sendMessage($recipient, $message));
        }

        $this->json($result);
    }

    public function messageBirdReport()
    {
        //$data = json_decode($this->src->get('BODY'), true);
        $result = $this->model->saveReport($this->app->get('BODY'));
        $this->json($result);
    }
}