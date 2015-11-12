<?php

namespace ZEN\FilesBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class PhotoType extends AbstractType {

    
    protected $childGalleryId;
    


    public function __construct($childGalleryId) {
        
        $this->childGalleryId = $childGalleryId;

    }
    
    
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        
        $builder->add('file', 'file', array(
            'attr' => array(
                'multiple' => 'multiple',
                'class' => 'field-upload',
                'accept' => 'image/jpeg'
            ),
//             'mapped' => false
        ));
       
        $builder->add('childGalleryId', 'hidden', array(
            'data' => $this->childGalleryId,
            'mapped' => false
        ));
        
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver) {
        $resolver->setDefaults(array(
            'data_class' => 'ZEN\FilesBundle\Entity\Photo',
            'translation_domain' => 'form_photo'
        ));
    }

    /**
     * @return string
     */
    public function getName() {
        return 'form_photo';
    }

}
