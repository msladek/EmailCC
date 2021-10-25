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
  
  protected $templateIdentifier;
  
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
  
  public function beforeSetTemplateIdentifier($subject, $templateIdentifier)
  {
    $this->templateIdentifier = $templateIdentifier;
    return null;
  }
  
  public function beforeSetTemplateVars($subject, $vars)
  {
    if (isset($vars['order'])) {
      $this->order = $vars['order'];
    }
    return null;
  }

  public function beforeGetTransport(
      \Magento\Framework\Mail\Template\TransportBuilder $subject
  ) {
    try {
      if ($this->isCurrentOrderEmail()) {
        $this->logger->debug((string) __('EmailCC - collecting CC/BCC'));
        foreach ($this->getCustomerEmailCopyTo() as $ccEmailAddress) {
          $subject->addCc(trim($ccEmailAddress));
          $this->logger->debug((string) __('EmailCC - added CC: %1', trim($ccEmailAddress)));
        }
        foreach ($this->getOrderEmailCopyTo() as $bccEmailAddress) {
          $subject->addBcc(trim($bccEmailAddress));
          $this->logger->debug((string) __('EmailCC - added BCC: %1', trim($bccEmailAddress)));
        }
      } else {
        $this->logger->debug((string) __('EmailCC - skip non-order email'));
      }
    } catch (\Exception $e) {
      $this->logger->error((string) __('EmailCC - failed: %1', $e->getMessage()));
    }
    return null;
  }

  /**
   * Check if current template matches order template
   * @return boolean
   */
  public function isCurrentOrderEmail()
  {
    return $this->templateIdentifier == $this->getConfigValue('sales_email/order/template')
        || $this->templateIdentifier == $this->getConfigValue('sales_email/order/guest_template');
  }

  /**
   * Return email copy_to list from customer
   * @return array
   */
  public function getCustomerEmailCopyTo()
  {
    $customer = $this->getCustomerFromOrder();
    if ($customer) {
      $emailCc = $customer->getCustomAttribute('email_cc');
      $customerEmailCC = $emailCc ? $emailCc->getValue() : null;
    } else {
      $this->logger->debug('EmailCC - no customer found');
    }
    if (!empty($customerEmailCC)) {
      return explode(',', trim($customerEmailCC));
    }
    return [];
  }
  
  /**
   * Get customer id from the current order
   * @return customer
   */
  public function getCustomerFromOrder()
  {
    if ($this->order) {
      $customerId = $this->order->getCustomerId();
      return $this->getCustomerById($customerId);
    }
    return null;
  }

  /**
   * Get customer by Id.
   * @param int $customerId
   * @return \Magento\Customer\Model\Data\Customer
   */
  public function getCustomerById($customerId)
  {
    if ($customerId) {
      try {
        return $this->customerRepositoryInterface->getById($customerId);
      } catch (\Exception $e) {
        $this->logger->critical($e);
      }
    }
    return null;
  }

  /**
   * Return email copy_to list from order config
   * @return array
   */
  public function getOrderEmailCopyTo()
  {
    $orderEmailCc = $this->getConfigValue('sales_email/order/copy_to');
    if (!empty($orderEmailCc)) {
      return explode(',', trim($orderEmailCc));
    }
    return [];
  }

  public function getConfigValue($configPath)
  {
    if ($this->scopeConfig) {
      return $this->scopeConfig->getValue(
          $configPath,
          \Magento\Store\Model\ScopeInterface::SCOPE_STORE
      );
    }
    return null;
  }
}
