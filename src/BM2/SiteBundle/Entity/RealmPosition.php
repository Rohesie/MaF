<?php 

namespace BM2\SiteBundle\Entity;


class RealmPosition {
        
	public function __toString() {
		return "{$this->id} ({$this->name})";
	}

	
}
