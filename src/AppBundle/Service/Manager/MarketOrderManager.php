<?php

namespace AppBundle\Service\Manager;

use AppBundle\Entity\ApiCredentials;
use AppBundle\Entity\Corporation;
use AppBundle\Entity\MarketOrder;
use AppBundle\Entity\MarketOrderGroup;
use Doctrine\Bundle\DoctrineBundle\Registry;
use \EveBundle\Repository\Registry as EveRegistry;
use Tarioch\PhealBundle\DependencyInjection\PhealFactory;

class MarketOrderManager extends AbstractManager implements DataManagerInterface, MappableDataManagerInterface {

    protected $pheal;

    public function __construct(PhealFactory $pheal, EveRegistry $registry, Registry $doctrine){
        parent::__construct($doctrine, $registry);
        $this->pheal = $pheal;
    }

    public function getMarketOrders(Corporation $corporation){

        $apiKey = $this->doctrine->getRepository('AppBundle:ApiCredentials')
            ->getActiveKey($corporation);

        if ($apiKey === null){
            throw new \Exception('No active api key for corp' . $corporation->getId() .' found');
        }

        $client = $this->getClient($apiKey);

        $orders = $client->MarketOrders([
            'characterID' => $apiKey->getEveCharacterId()
        ]);

        $marketOrderGroup = $this->mapList($orders->orders, [ 'corp' => $corporation ]);

        $corporation->addMarketOrderGroup($marketOrderGroup);

        return $marketOrderGroup;

    }

    public function mapList($orders, array $options){
        $marketGroup = new MarketOrderGroup();

        $corp = $options['corp'] ? $options['corp'] : false;

        if (!$corp instanceof Corporation){
            throw new \OptionDefinitionException(sprintf('Option corp required and must by of type %s', get_class(new Corporation())));
        }

        foreach ($orders as $o){
            $order = $this->mapItem($o);
            $marketGroup->addMarketOrder($order);

        }

        return $marketGroup;
    }

    public function mapItem($order){
        $marketOrder = new MarketOrder();

        $marketOrder->setOrderId($order->orderID)
            ->setPlacedById($order->charID)
            ->setPlacedAtId($order->stationID)
            ->setTotalVolume($order->volEntered)
            ->setVolumeRemaining($order->volRemaining)
            ->setState($order->orderState)
            ->setTypeId($order->typeID)
            ->setOrderRange($order->range)
            ->setAccountKey($order->accountKey)
            ->setDuration($order->duration)
            ->setEscrow($order->escrow)
            ->setPrice($order->price)
            ->setBid($order->bid)
            ->setIssued(new \DateTime($order->issued));

        return $marketOrder;

    }

    public function getClient(ApiCredentials $key, $scope = 'corp'){
        $client = $this->pheal->createEveOnline(
            $key->getApiKey(),
            $key->getVerificationCode()
        );

        $client->scope = $scope;

        return $client;
    }
}
