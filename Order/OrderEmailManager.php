<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Sulu\Bundle\Sales\OrderBundle\Order;

use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\Sales\OrderBundle\Api\ApiOrderInterface;
use Sulu\Component\Contact\Model\ContactInterface;
use Sulu\Component\Security\Authentication\UserInterface;

class OrderEmailManager
{
    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var OrderPdfManager
     */
    protected $pdfManager;

    /**
     * @var \Swift_Mailer
     */
    protected $mailer;

    /**
     * @var string
     */
    protected $templateFooterHtmlPath;

    /**
     * @var string
     */
    protected $templateFooterTxtPath;

    /**
     * @var string
     */
    protected $templateCustomerConfirmationPath;

    /**
     * @var string
     */
    protected $templateShopownerConfirmationPath;

    /**
     * @var bool
     */
    protected $sendCustomerEmailConfirmation;

    /**
     * @var bool
     */
    protected $sendShopownerEmailConfirmation;


    /**
     * @param \Twig_Environment $twig
     * @param \Swift_Mailer $mailer
     * @param OrderPdfManager $pdfManager
     * @param string $emailFrom
     * @param string $confirmationRecipientEmailAddress
     * @param string $templateCustomerConfirmationPath
     * @param string $templateShopownerConfirmationPath
     * @param string $templateFooterHtmlPath
     * @param string $templateFooterTxtPath
     * @param bool $sendEmailConfirmationToShopowner
     * @param bool $sendEmailConfirmationToCustomer
     */
    public function __construct(
        \Twig_Environment $twig,
        \Swift_Mailer $mailer,
        OrderPdfManager $pdfManager,
        $emailFrom,
        $confirmationRecipientEmailAddress,
        $templateCustomerConfirmationPath,
        $templateShopownerConfirmationPath,
        $templateFooterHtmlPath,
        $templateFooterTxtPath,
        $sendEmailConfirmationToShopowner,
        $sendEmailConfirmationToCustomer
    ) {
        // services
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->pdfManager = $pdfManager;
        // email addresses
        $this->emailFrom = $emailFrom;
        $this->confirmationRecipientEmailAddress = $confirmationRecipientEmailAddress;
        // templates
        $this->templateCustomerConfirmationPath = $templateCustomerConfirmationPath;
        $this->templateShopownerConfirmationPath = $templateShopownerConfirmationPath;
        $this->templateFooterTxtPath = $templateFooterTxtPath;
        $this->templateFooterHtmlPath = $templateFooterHtmlPath;
        // define if emails should be sent
        $this->sendEmailConfirmationToShopowner = $sendEmailConfirmationToShopowner;
        $this->sendEmailConfirmationToCustomer = $sendEmailConfirmationToCustomer;
    }

    /**
     * Sends a confirmation email to the shop-owner
     *
     * @param null|string $recipient
     * @param ApiOrderInterface $apiOrder
     * @param ContactInterface $customerContact
     *
     * @return bool
     */
    public function sendShopownerConfirmation(
        $recipient,
        ApiOrderInterface $apiOrder,
        ContactInterface $customerContact = null
    ) {
        if (!$this->sendEmailConfirmationToShopowner) {
            return false;
        }

        if (empty($recipient)) {
            // fallback address for shop-owner order confirmations
            $recipient = $this->confirmationRecipientEmailAddress;
        }

        return $this->sendConfirmationEmail(
            $recipient,
            $apiOrder,
            $this->templateShopownerConfirmationPath,
            $customerContact
        );
    }

    /**
     * Sends a confirmation email to the customer
     *
     * @param string $recipient
     * @param ApiOrderInterface $apiOrder
     * @param ContactInterface $customerContact
     *
     * @return bool
     */
    public function sendCustomerConfirmation(
        $recipient,
        ApiOrderInterface $apiOrder,
        ContactInterface $customerContact = null
    ) {
        if (!$this->sendEmailConfirmationToCustomer) {
            return false;
        }

        return $this->sendConfirmationEmail(
            $recipient,
            $apiOrder,
            $this->templateCustomerConfirmationPath,
            $customerContact
        );
    }

    /**
     * @param string $recipient The email-address of the customer
     * @param ApiOrderInterface $apiOrder
     * @param string $templatePath Template to render
     * @param ContactInterface|null $customerContact
     *
     * @return bool
     */
    public function sendConfirmationEmail(
        $recipient,
        ApiOrderInterface $apiOrder,
        $templatePath,
        ContactInterface $customerContact = null
    ) {
        if (empty($recipient)) {
            $this->writeLog('No recipient specified.');

            return false;
        }

        $tmplData = array(
            'order' => $apiOrder,
            'contact' => $customerContact,
        );

        return $this->sendMail($recipient, $templatePath, $tmplData, $apiOrder);
    }

    /**
     * Sends an email
     *
     * @param string $recipient
     * @param string $templatePath
     * @param array $data
     * @param ApiOrderInterface $apiOrder
     * @param array $blindCopyRecipients Recipients to send bcc
     *
     * @return bool
     */
    public function sendMail(
        $recipient,
        $templatePath,
        $data = array(),
        ApiOrderInterface $apiOrder = null,
        $blindCopyRecipients = array()
    ) {
        $tmplData = array_merge(
            $data,
            array(
                'footerTxt' => $this->templateFooterTxtPath,
                'footerHtml' => $this->templateFooterHtmlPath,
            )
        );

        $template = $this->twig->loadTemplate($templatePath);
        $subject = $template->renderBlock('subject', $tmplData);

        $emailBodyText = $template->renderBlock('body_text', $tmplData);
        $emailBodyHtml = $template->renderBlock('body_html', $tmplData);

        /** @var \Swift_Message $message */
        $message = \Swift_Message::newInstance()
            ->setSubject($subject)
            ->setFrom($this->emailFrom)
            ->setTo($recipient)
            ->setBody($emailBodyText, 'text/plain')
            ->addPart($emailBodyHtml, 'text/html');
        
        // add blind copy recipients
        foreach ($blindCopyRecipients as $bcc) {
            $message->addBcc($bcc);
        }

        // add pdf if order is supplied
        if ($apiOrder) {
            $pdf = $this->pdfManager->createOrderConfirmation($apiOrder);
            $pdfFileName = $this->pdfManager->getPdfName($apiOrder);
            // now send mail
            $attachment = \Swift_Attachment::newInstance()
                ->setFilename($pdfFileName)
                ->setContentType('application/pdf')
                ->setBody($pdf);
            $message->attach($attachment);
        }

        $failedRecipients = array();
        $this->mailer->send($message, $failedRecipients);

        if (count($failedRecipients) > 0) {
            $this->writeLog('Could not send mail to the following recipients: ' . join(', ', $failedRecipients));

            return false;
        }

        return true;
    }

    /**
     * Get Email address of a user; fallback to contact / account if not defined
     *
     * @param UserInterface $user
     * @param bool $useFallback
     *
     * @return null|string
     */
    public function getEmailAddressOfUser(UserInterface $user, $useFallback = true)
    {
        // take users email address
        $userEmail = $user->getEmail();
        if ($userEmail) {
            return $userEmail;
        }

        // fallback: get contacts / accounts main-email
        $contact = $user->getContact();
        if ($useFallback && $contact) {
            return $this->getEmailAddressOfContact($contact);
        }

        return null;
    }

    /**
     * Gets email address of a contact
     *
     * @param ContactInterface $contact
     * @param bool $useFallback
     *
     * @return string|null
     */
    public function getEmailAddressOfContact(ContactInterface $contact, $useFallback = true)
    {
        // take contacts main-email
        $contactMainEmail = $contact->getMainEmail();
        if ($contact && $contactMainEmail) {
            return $contactMainEmail;
        }

        // fallback take contact's main-account main-email
        $account = $contact->getAccount();
        if ($useFallback && $account) {
            return $this->getEmailAddressOfAccount($account);
        }

        return null;
    }

    /**
     * Get Email-Address of account
     *
     * @param AccountInterface $account
     *
     * @return string|null
     */
    public function getEmailAddressOfAccount(AccountInterface $account)
    {
        $accountMainEmail = $account->getMainEmail();
        if ($accountMainEmail) {
            return $accountMainEmail;
        }

        return null;
    }

    /**
     * Writes a new line to mail error log
     *
     * @param string $message
     */
    private function writeLog($message)
    {
        $fileName = 'app/logs/mail/error.log';

        $log = sprintf("[%s]: %s\n", date_format(new \DateTime(), 'Y-m-d H:i:s'), $message);
        file_put_contents($fileName, $log, FILE_APPEND);
    }
}