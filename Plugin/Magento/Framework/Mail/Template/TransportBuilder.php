<?php
/**
 * Add email cc field to customer account area. Transactional emails are also sent to this address.
 * Copyright (C) 2018 Dominic Xigen
 *
 * This file included in Xigen/CC is licensed under OSL 3.0
 *
 * http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * Please see LICENSE.txt for the full text of the OSL 3.0 license
 */

namespace Xigen\CC\Plugin\Magento\Framework\Mail\Template;

/**
 * Plugin to add customer email cc
 */
class TransportBuilder
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepositoryInterface;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    
    protected $order;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
        \Magento\Customer\Model\Session $customerSession,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
    }
    
    public function beforeSetTemplateVars($subject, $vars)
    {
        if (isset($vars['order'])) {
            $this->order = $vars['order'];
        }
        return ['vars' => $vars];
    }

    public function beforeGetTransport(
        \Magento\Framework\Mail\Template\TransportBuilder $subject
    ) {
        try {
            foreach ($this->getCustomerEmailCopyTo() as $ccEmailAddress) {
                $subject->addCc(trim($ccEmailAddress));
                $this->logger->debug((string) __('Added CC: %1', trim($ccEmailAddress)));
            }
            foreach ($this->getOrderEmailCopyTo() as $bccEmailAddress) {
                $subject->addBcc(trim($bccEmailAddress));
                $this->logger->debug((string) __('Added BCC: %1', trim($bccEmailAddress)));
            }
        } catch (\Exception $e) {
            $this->logger->error((string) __('Failure to add CC: %1', $e->getMessage()));
        }
        return [];
    }

    /**
     * Return email copy_to list from customer
     * @return array
     */
    public function getCustomerEmailCopyTo()
    {
        if(!empty($this->order)) {
          $customerId = $this->order->getCustomerId();
        } else {
          $customerId = $this->customerSession->getCustomer()->getId();
        }
        if (!empty($customerId)) {
          $customer = $this->getCustomerById($customerId);
        }
        $customer = $this->getCustomerById($customerId);
        if (!empty($customer)) {
          $emailCc = $customer->getCustomAttribute('email_cc');
          $customerEmailCC = $emailCc ? $emailCc->getValue() : null;
        }
        if (!empty($customerEmailCC)) {
            return explode(',', trim($customerEmailCC));
        }
        return [];
    }

    /**
     * Get customer by Id.
     * @param int $customerId
     * @return \Magento\Customer\Model\Data\Customer
     */
    public function getCustomerById($customerId)
    {
        try {
            return $this->customerRepositoryInterface->getById($customerId);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return false;
        }
    }

    /**
     * Return email copy_to list from sales
     * @return array
     */
    public function getOrderEmailCopyTo()
    {
        $salesEmailCc = $this->scopeConfig->getValue(
            'sales_email/order/copy_to',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if (!empty($salesEmailCc)) {
            return explode(',', trim($salesEmailCc));
        }
        return [];
    }
}
