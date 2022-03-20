<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

use BM2\SiteBundle\Entity\AssociationType;

class AssocDeityType extends AbstractType {

	private $aspects;

	public function __construct($aspects) {
		$this->aspects = $aspects;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'newdeity_1779',
			'translation_domain' => 'orgs'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$aspects = $this->aspects;
		$builder->add('name', TextType::class, array(
			'label'=>'deity.form.new.name',
			'required'=>true,
			'attr' => array(
				'size'=>20,
				'maxlength'=>40,
				'title'=>'deity.help.name'
			)
		));
		$builder->add('aspects', EntityType::class, array(
			'label'=>'deity.form.new.aspects',
			'required'=>true,
			'attr' => array('title'=>'deity.help.aspects'),
			'class' => 'BM2SiteBundle:AspectType',
			'choice_translation_domain' => true,
			'choice_label' => 'name',
			'multiple' => true,
			'expanded' => false,
			'choices' => $aspects
		));
		$builder->add('description', TextareaType::class, array(
			'label'=>'deity.form.description.full',
			'attr' => array('title'=>'deity.help.desc'),
			'required'=>true,
		));
		$builder->add('words', TextareaType::class, array(
			'label'=>'deity.form.new.words',
			'attr' => array('title'=>'deity.help.words'),
			'required'=>false,
		));
		$builder->add('submit', SubmitType::class, array('label'=>'deity.form.submit'));
	}

	public function getName() {
		return 'deitycreate';
	}
}
