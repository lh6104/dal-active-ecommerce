<?php
namespace Boolfly\ZaloPay\Gateway\Command;

use Boolfly\ZaloPay\Gateway\Helper\DebugLog;
use Boolfly\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Class GetPayUrlCommand
 *
 * @package Boolfly\ZaloPay\Gateway\Command
 */
class GetPayUrlCommand implements CommandInterface
{
    /**
     * @var BuilderInterface
     */
    private $requestBuilder;

    /**
     * @var TransferFactoryInterface
     */
    private $transferFactory;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DebugLog
     */
    private $debugLog;

    /**
     * Constructor
     *
     * @param BuilderInterface         $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface          $client
     * @param ValidatorInterface       $validator
     */
    public function __construct(
        BuilderInterface $requestBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $client,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        DebugLog $debugLog
    ) {
        $this->requestBuilder  = $requestBuilder;
        $this->transferFactory = $transferFactory;
        $this->client          = $client;
        $this->validator       = $validator;
        $this->logger          = $logger;
        $this->debugLog        = $debugLog;
    }

    /**
     * @param array $commandSubject
     * @return array|null
     * @throws CommandException
     * @throws ClientException
     * @throws ConverterException
     */
    public function execute(array $commandSubject)
    {
        $requestData = $this->buildRequestData($commandSubject);
        $this->logger->debug('ZaloPay create order request', $this->maskSensitiveData($requestData));
        $this->debugLog->log('create_order_request', $this->maskSensitiveData($requestData));

        // Build the transfer object
        $transferO = $this->transferFactory->create($requestData);

        // Send the request via the client
        $response = $this->client->placeRequest($transferO);
        $this->logger->debug('ZaloPay create order response', is_array($response) ? $response : ['response' => $response]);
        $this->debugLog->log('create_order_response', is_array($response) ? $response : ['response' => $response]);

        // Validate the response
        $result = $this->validator->validate(array_merge($commandSubject, ['response' => $response]));

        if (!$result->isValid()) {
            throw new CommandException(__(
                implode("\n", $result->getFailsDescription())
                . $this->formatGatewayError($response)
            ));
        }

        // Check for the expected response key before returning it
        if (!isset($response[AbstractResponseValidator::PAY_URL])) {
            throw new CommandException(__('Invalid response from payment gateway.%1', $this->formatGatewayError($response)));
        }

        // Return the pay URL in an array
        return [
            AbstractResponseValidator::PAY_URL => $response[AbstractResponseValidator::PAY_URL]
        ];
    }

    /**
     * @param array $commandSubject
     * @return array
     */
    public function buildRequestData(array $commandSubject)
    {
        return $this->requestBuilder->build($commandSubject);
    }

    private function formatGatewayError($response): string
    {
        if (!is_array($response)) {
            return '';
        }

        $parts = [];
        foreach ([
            AbstractResponseValidator::RETURN_CODE,
            AbstractResponseValidator::RETURN_MESSAGE,
            AbstractResponseValidator::SUB_RETURN_CODE,
            AbstractResponseValidator::SUB_RETURN_MESSAGE,
        ] as $key) {
            if (isset($response[$key])) {
                $parts[] = $key . '=' . $response[$key];
            }
        }

        return $parts ? ' (' . implode(', ', $parts) . ')' : '';
    }

    private function maskSensitiveData(array $data): array
    {
        if (isset($data['mac'])) {
            $data['mac'] = '[masked]';
        }

        return $data;
    }
}
