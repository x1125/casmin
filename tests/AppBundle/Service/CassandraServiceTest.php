<?php

namespace Tests\AppBundle\Service;

use AppBundle\Service\CqlshService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CqlshServiceTest extends WebTestCase
{
    public function testParseFieldTypeFromArray()
    {
        // simple varchar test
        $type = CqlshService::parseFieldTypeFromArray(array('varchar'));
        $this->assertTrue($type === 'varchar');

        // simple list test
        $type = CqlshService::parseFieldTypeFromArray(array(
            'list' => array(
                0 => 'int',
                1 => 'varchar'
            )
        ));
        $this->assertTrue($type === 'list<int,varchar>');

        // nested list test
        $type = CqlshService::parseFieldTypeFromArray(array(
            'list' => array(
                'list' => array(
                    'list' => 'int'
                )
            )
        ));
        $this->assertTrue($type === 'list<list<list<int>>>');

        // simple map test
        $type = CqlshService::parseFieldTypeFromArray(array(
            'map' => array(
                0 => 'ascii',
                1 => 'bigint'
            )
        ));
        $this->assertTrue($type === 'map<ascii,bigint>');

        // nested map test
        $type = CqlshService::parseFieldTypeFromArray(array(
            'map' => array(
                0 => 'double',
                1 => array(
                    'map' => array(
                        0 => 'ascii',
                        1 => 'ascii'
                    )
                )
            )
        ));
        $this->assertTrue($type === 'map<double,map<ascii,ascii>>');

        // simple tuple test
        $type = CqlshService::parseFieldTypeFromArray(array(
            'tuple' => array(
                0 => 'ascii',
                1 => 'bigint',
                2 => 'boolean'
            )
        ));
        $this->assertTrue($type === 'tuple<ascii,bigint,boolean>');

        // nested tuple test
        $type = CqlshService::parseFieldTypeFromArray(array(
            'tuple' => array(
                0 => 'ascii',
                1 => array(
                    'list' => 'int'
                ),
                2 => array(
                    'map' => array(
                        'varchar',
                        'uuid'
                    )
                )
            )
        ));
        $this->assertTrue($type === 'tuple<ascii,list<int>,map<varchar,uuid>>');

        // insane nested tuple, list and map
        $type = CqlshService::parseFieldTypeFromArray(array(
            'tuple' => array(
                0 => array(
                    'tuple' => array(
                        0 => 'varchar',
                        1 => 'int'
                    )
                ),
                1 => array(
                    'list' => array(
                        'tuple' => array(
                            'map' => array(
                                0 => 'varchar',
                                1 => 'int'
                            )
                        )
                    )
                ),
                2 => array(
                    'map' => array(
                        'varchar',
                        'uuid'
                    )
                )
            )
        ));
        $this->assertTrue($type === 'tuple<tuple<varchar,int>,list<tuple<map<varchar,int>>>,map<varchar,uuid>>');
    }
}
