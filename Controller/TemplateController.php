<?php

namespace Sulu\Bundle\Sales\OrderBundle\Controller;

use Sulu\Bundle\Sales\OrderBundle\Api\OrderStatus;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sulu\Component\Rest\RestController;
use Hateoas\Representation\CollectionRepresentation;

class TemplateController extends RestController
{

    static $termsOfPaymentEntityName = 'SuluContactBundle:TermsOfPayment';
    static $termsOfDeliveryEntityName = 'SuluContactBundle:TermsOfDelivery';
    static $orderStatusEntityName = 'SuluSalesOrderBundle:OrderStatus';

    /**
     * Returns Template for list
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function orderListAction()
    {
        return $this->render(
            'SuluSalesOrderBundle:Template:order.list.html.twig'
        );
    }

    /**
     * Returns Template for list
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function orderFormAction()
    {
        return $this->render(
            'SuluSalesOrderBundle:Template:order.form.html.twig',
            array(
                'systemUser' => $this->getSystemUserArray(),
                'termsOfPayment' => $this->getTermsArray(self::$termsOfPaymentEntityName),
                'termsOfDelivery' => $this->getTermsArray(self::$termsOfDeliveryEntityName),
                'orderStatus' => $this->getOrderStatus()
            )
        );
    }

    /**
     * returns all sulu system users
     * @return array
     */
    public function getSystemUserArray()
    {
        $repo = $this->get('sulu_security.user_repository');
        $users = $repo->getUserInSystem();
        $contacts = [];

        foreach ($users as $user) {
            $contact = $user->getContact();
            $contacts[] = array(
                'id' => $contact->getId(),
                'fullName' => $contact->getFullName()
            );
        }
        return $contacts;
    }

    /**
     * returns Terms Of Payment / Delivery
     * @param $entityName
     * @return array
     */
    public function getTermsArray($entityName)
    {
        $terms = $this->getDoctrine()->getRepository($entityName)->findAll();
        $termsArray = [];

        foreach ($terms as $term) {
            $termsArray[] = array(
                'id' => $term->getId(),
                'name' => $term->getTerms()
            );
        }
        return $termsArray;
    }

    /**
     * returns array of order statuses
     * @return array
     */
    public function getOrderStatus() {
        $statuses = $this->getDoctrine()->getRepository(self::$orderStatusEntityName)->findAll();
        $locale = $this->getUser()->getLocale();
        $statusArray = [];

        foreach ($statuses as $statusEntity) {
            $status = new OrderStatus($statusEntity, $locale);
            $statusArray[] = array(
                'id' => $status->getId(),
                'status' => $status->getStatus()
            );
        }
        return $statusArray;
    }
}