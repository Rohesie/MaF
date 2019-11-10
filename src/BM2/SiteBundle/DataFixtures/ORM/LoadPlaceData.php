<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\PlaceType;

class LoadPlaceData extends AbstractFixture implements OrderedFixtureInterface {

	private $placetypes = array(
		'academy'	=> array('requires' => 'academy',	'visible' => false),
		'arena'		=> array('requires' => 'arena',		'visible' => false),
		'capital'	=> array('requires' => 'ruler',		'visible' => true),
		'castle'	=> array('requires' => 'castle',	'visible' => true),
		'cave'		=> array('requires' => '',		'visible' => true),
		'embassy'	=> array('requires' => 'ambassador',	'visible' => true, 'pop' =>	2)
		'fort'		=> array('requires' => 'fort',		'visible' => true),
		'home'		=> array('requires' => 'dynasty head',	'visible' => true),
		'inn'		=> array('requires' => 'inn',		'visible' => true),
		'library'	=> array('requires' => '',		'visible' => true),
		'monument'	=> array('requires' => 'lord',		'visible' => true),
		'plaza'		=> array('requires' => 'lord',		'visible' => true),
		'portal' 	=> array('requires' => 'magic',		'visible' => false),
		'passage'	=> array('requires' => 'warren',	'visible' => false),
		'track'		=> array('requires' => 'track',		'visible' => true),
		'tavern'	=> array('requires' => 'tavern',	'visible' => true),
		'tournament'	=> array('requires' => 'lord',		'visible' => false, 'pop' =>	10)
	);

	private $placesubtypes = array(
		'memorial'	=> array('type' =>	'monument'),
		'statue'	=> array('type' =>	'monument'),
		'obelisk'	=> array('type' =>	'monument'),
		'market'	=> array('type' =>	'plaza'),
		'scenic'	=> array('type' =>	'plaza'),
		'event'		=> array('type' =>	'plaza'),
	);

	private $placeupgradetypes = array(
		'horses'		=> array('type' =>	'track'),
		'chariot'		=> array('type' =>	'track'),
		'small cages'		=> array('type' =>	'arena'),
		'large cages'		=> array('type' =>	'arena', 'requires' =>	'small cages'),
		'traps'			=> array('type' =>	'arena'),
		'melee'			=> array('type' =>	'tournament',	'pop' =>	10),
		'ranged'		=> array('type' =>	'tournament',	'pop' =>	10),
		'joust'			=> array('type' =>	'tournament',	'pop' =>	10),
		'small guard'		=> array('type' =>	'embassy',	'pop' =>	10),
		'medium guard'		=> array('type' =>	'embassy',	'pop' =>	15, 'requires' =>	'small guard'),
		'large guard'		=> array('type' =>	'embassy',	'pop' =>	25, 'requires' =>	'medium guard'),
		'local guard'		=> array('type' =>	'embassy',	'pop' =>	25),
		'regional guard'	=> array('type' =>	'embassy',	'pop' =>	50, 'requires' =>	'local guard'),
		'royal guard'		=> array('type' =>	'embassy',	'pop' =>	125, 'requires' =>	'regional guard'),
		'imperial guard'	=> array('type' =>	'embassy',	'pop' =>	200, 'requires' =>	'royal guard')
	);

	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 1000; // or anywhere, really
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		# Load place types.
		foreach ($this->placetypes as $name=>$data) {
			$type = $manager->getRepository('BM2SiteBundle:PlaceType')->findOneByName($name);
			if (!$type) {
				$type = new PlaceType();
				$manager->persist($type);
			}
			$type->setName($name);
			if ($data['requires']) {
				$type->setRequires($data['requires']);
			}
			$type->setVisible($data['visible']);
			if ($data['pop']) {
				$type->setWorkers($data['pop']);
			} else {
				$type->setWorkers(0);
			}
			$manager->persist($type);
		}
		$manager->flush();

		# Load Place subtypes.
		foreach ($this->placesubtypes as $name=>$data) {
			$type = $manager->getRepository('BM2SiteBundle:PlaceSubType')->findOneByName($name);
			if (!$type) {
				$type = new PlaceSubType();
				$manager->persist($type);
			}
			$type->setName($name);
			$type->setPlaceType($manager->getRepository('BM2SiteBundle:PlaceType')->findOneByName($data['type']));
			$manager->persist($type);
		}
		# Load Place upgrades.
		foreach ($this->placeupgradetypes as $name=>$data) {
			$type = $manager->getRepository('BM2SiteBundle:PlaceUpgradeType')->findOneByName($name);
			if (!$type) {
				$type = new PlaceUpgradeType();
				$manager->persist($type);
			}
			$type->setName($name);
			$type->setPlaceType($manager->getRepository('BM2SiteBundle:PlaceType')->findOneByName($data['type']));
			if ($data['requires']) {
				$type->setRequires($data['requires']);
			}
			if ($data['pop']) {
				$type->setWorkers($data['pop']);
			} else {
				$type->setWorkers(0);
			}
			$manager->persist($type);
		}
		$manager->flush();
	}
}
