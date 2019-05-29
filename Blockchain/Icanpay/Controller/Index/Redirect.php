<?php


namespace Blockchain\Icanpay\Controller\Index;

use Magento\Framework\Controller\ResultFactory;

class Redirect extends \Magento\Framework\App\Action\Action
{

    protected $resultPageFactory;
    protected $_coreSession;
    protected $_responseFactory;
    protected $_redirect;
    private $baseUrl;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Session\SessionManagerInterface $coreSession,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\App\Response\Http $redirect,
        \Magento\Framework\UrlInterface $baseUrl

    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->_coreSession = $coreSession;
        $this->_responseFactory = $responseFactory;
        $this->_redirect = $redirect;
        $this->resultFactory = $context->getResultFactory();
        $this->baseUrl = $baseUrl;
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $url = $this->baseUrl->getUrl('checkout/onepage/success');
        $resultRedirect->setUrl($url);
        return $resultRedirect;
    }
}
