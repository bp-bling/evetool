<?php

namespace AppBundle\Service\DataManager\Corporation;

use AppBundle\Entity\Corporation;
use AppBundle\Entity\CorporationTitle;
use AppBundle\Service\DataManager\AbstractManager;
use AppBundle\Service\DataManager\DataManagerInterface;
use AppBundle\Service\DataManager\MappableDataManagerInterface;

class TitleManager extends AbstractManager implements DataManagerInterface, MappableDataManagerInterface {

    public function updateTitles(Corporation $corporation){

        $apiKey = $this->getApiKey($corporation);

        $client = $this->getClient($apiKey);

        $titles = $client->Titles([
            'characterID' => $apiKey->getEveCharacterId()
        ]);

        $this->mapList($titles->titles, ['corp' => $corporation]);

    }

    public function mapList($items, array $options){
        $corp = $options['corp'];

        foreach ($items as $i){
            $item = $this->mapItem($i->toArray());
            $corp->addTitle($item);
        }
    }

    public function mapItem($item){
        $title = new CorporationTitle();

        $title->setEveTitleId($item['titleID'])
            ->setTitleName($item['titleName'])
            ->setRoles($item['roles'])
            ->setGrantableRoles($item['grantableRoles'])
            ->setRolesAtHq($item['rolesAtHQ'])
            ->setGrantableRolesAtHq($item['grantableRolesAtHQ'])
            ->setRolesAtOther($item['rolesAtOther'])
            ->setGrantableRolesAtOther($item['grantableRolesAtOther']);

        return $title;
    }

    public static function getName(){
        return 'title_manager';
    }

}
