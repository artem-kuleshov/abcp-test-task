<?php

namespace NW\WebService\References\Operations\Notification;

use NW\WebService\References\Operations\Notification\Classes\ReferencesOperation;
use NW\WebService\References\Operations\Notification\Classes\NotificationEvents;
use NW\WebService\References\Operations\Notification\Classes\Seller;
use NW\WebService\References\Operations\Notification\Classes\Contractor;

class TsReturnOperation extends ReferencesOperation
{
    const TYPE_NEW = 1;
    const TYPE_CHANGE = 2;

    private $data = [];

    public function __construct($data_in)
    {
        $this->result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail' => false,
            'notificationClientBySms' => [
                'isSent' => false,
                'message' => '',
            ],
        ];

        $this->setData($data_in);
    }

    private function setData($data_in): void
    {
        $data = (array)$data_in;

        $this->data = [
            'resellerId' => isset($data['resellerId']) ? (int)$data['resellerId'] : null,
            'notificationType' => isset($data['notificationType']) ? (int)$data['notificationType'] : null,
            'clientId' => isset($data['clientId']) ? (int)$data['clientId'] : null,
            'creatorId' => isset($data['creatorId']) ? (int)$data['creatorId'] : null,
            'expertId' => isset($data['expertId']) ? (int)$data['expertId'] : null,
            'complaintId' => isset($data['complaintId']) ? (int)$data['complaintId'] : null,
            'complaintNumber' => isset($data['complaintNumber']) ? (string)$data['complaintNumber'] : '',
            'consumptionId' => isset($data['consumptionId']) ? (int)$data['consumptionId'] : null,
            'consumptionNumber' => isset($data['consumptionNumber']) ? (string)$data['consumptionNumber'] : '',
            'agreementNumber' => isset($data['agreementNumber']) ? (string)$data['agreementNumber'] : '',
            'date' => isset($data['date']) ? (string)$data['date'] : '',
            'differences' => [
                'to' => isset($data['differences']['to']) ? (int)$data['differences']['to'] : null,
                'from' => isset($data['differences']['from']) ? (int)$data['differences']['from'] : null
            ]
        ];
    }

    /**
     * @throws \Exception
     */
    public function doOperation(): array
    {
        if (!$this->data['resellerId']) {
            $this->result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $this->result;
        }

        if (!$this->data['notificationType']) {
            throw new \Exception('Empty notificationType', 400);
        }

        $reseller = Seller::getById($this->data['resellerId']);
        if ($reseller === null) {
            throw new \Exception('Seller not found!', 404);
        }

        $this->client = Contractor::getById($this->data['clientId']);
        if ($this->client === null || $this->client->type !== Contractor::TYPE_CUSTOMER || $this->client->Seller->id !== $this->data['resellerId']) {
            throw new \Exception('сlient not found!', 404);
        }

        $cFullName = $this->client->getFullName() ?: $this->client->name;

        $cr = Employee::getById($this->data['creatorId']);
        if ($cr === null) {
            throw new \Exception('Creator not found!', 404);
        }

        $et = Employee::getById($this->data['expertId']);
        if ($et === null) {
            throw new \Exception('Expert not found!', 404);
        }

        $differences = '';
        if ($this->data['notificationType'] === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $this->data['resellerId']);
        } elseif ($this->data['notificationType'] === self::TYPE_CHANGE && !empty($this->data['differences']['from']) && !empty($this->data['differences']['to'])) {
            $differences = __('PositionStatusHasChanged', [
                'FROM' => Status::getName($this->data['differences']['from']),
                'TO' => Status::getName($this->data['differences']['to']),
            ], $this->data['resellerId']);
        }

        $templateData = [
            'COMPLAINT_ID' => $this->data['complaintId'],
            'COMPLAINT_NUMBER' => $this->data['complaintNumber'],
            'CREATOR_ID' => $this->data['creatorId'],
            'CREATOR_NAME' => $cr->getFullName(),
            'EXPERT_ID' => $this->data['expertId'],
            'EXPERT_NAME' => $et->getFullName(),
            'CLIENT_ID' => $this->data['clientId'],
            'CLIENT_NAME' => $cFullName,
            'CONSUMPTION_ID' => $this->data['consumptionId'],
            'CONSUMPTION_NUMBER' => $this->data['consumptionNumber'],
            'AGREEMENT_NUMBER' =>$this->data['agreementNumber'],
            'DATE' => $this->data['date'],
            'DIFFERENCES' => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (!$tempData) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $emailFrom = getResellerEmailFrom();
        // Получаем email сотрудников из настроек
        $emails = getEmailsByPermit($this->data['resellerId'], 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                        'emailFrom' => $emailFrom,
                        'emailTo' => $email,
                        'subject' => __('complaintEmployeeEmailSubject', $templateData, $this->data['resellerId']),
                        'message' => __('complaintEmployeeEmailBody', $templateData, $this->data['resellerId']),
                    ],
                ], $this->data['resellerId'], NotificationEvents::CHANGE_RETURN_STATUS);
                
                $this->result['notificationEmployeeByEmail'] = true;
            }
        }
        
        if ($this->data['notificationType'] === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            $this->sendClientNotification($templateData, $emailFrom);
        }
        
        return $this->result;
    }

    // Шлём клиентское уведомление, только если произошла смена статуса
    private function sendClientNotification(array $templateData, string $emailFrom = '')
    {
        if (!empty($emailFrom) && !empty($this->client->email)) {
            MessagesClient::sendMessage([
                0 => [ // MessageTypes::EMAIL
                    'emailFrom' => $emailFrom,
                    'emailTo' => $this->client->email,
                    'subject' => __('complaintClientEmailSubject', $templateData, $this->data['resellerId']),
                    'message' => __('complaintClientEmailBody', $templateData, $this->data['resellerId']),
                ],
            ], $this->data['resellerId'], $this->client->id, NotificationEvents::CHANGE_RETURN_STATUS, $this->data['differences']['to']);

            $this->result['notificationClientByEmail'] = true;
        }

        if (!empty($this->client->mobile)) {
            $res = NotificationManager::send($this->data['resellerId'], $this->client->id, NotificationEvents::CHANGE_RETURN_STATUS, $this->data['differences']['to'], $templateData, $error);
            if ($res) {
                $this->result['notificationClientBySms']['isSent'] = true;
            }
            if (!empty($error)) {
                $this->result['notificationClientBySms']['message'] = $error;
            }
        }
    }
}
