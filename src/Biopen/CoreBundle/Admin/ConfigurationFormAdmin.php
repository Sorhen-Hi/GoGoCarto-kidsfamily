<?php
/**
 * @Author: Sebastian Castro
 * @Date:   2017-03-28 15:29:03
 * @Last Modified by:   Sebastian Castro
 * @Last Modified time: 2018-04-22 19:45:15
 */
namespace Biopen\CoreBundle\Admin;

use Biopen\CoreBundle\Admin\ConfigurationAbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollection;

class ConfigurationFormAdmin extends ConfigurationAbstractAdmin
{
    protected $baseRouteName = 'biopen_core_bundle_config_form_admin_classname';

    protected $baseRoutePattern = 'biopen/core/configuration-form';

    protected function configureFormFields(FormMapper $formMapper)
    {        
        $formMapper
            ->tab('Formulaire')  
                ->with('Configuration du formulaire', array('description' => "
                    <div class='text-and-iframe-container'><div class='iframe-container-aside'><iframe height='200' sandbox='allow-same-origin allow-scripts' src='https://video.colibris-outilslibres.org/videos/embed/2dd4dad3-63fa-4bb4-b48c-e518f8e56d36' frameborder='0' allowfullscreen></iframe></div>
                    Choisissez ici quels champs constituent un élement de votre base de donnée. 
                    <li>Choisissez bien l'attribut <b>Nom (unique)</b>, avec une valeur compréhensible.</li>
                    <li>Certains champs sont obligatoires (categories, titre, adresse). </li>
                    <li>Le champ <b>Email principal</b> sera utilisé pour envoyer des emails à l'élément référencé, pour lui indiquer qu'il a bien été ajouté sur le site, qu'il a été supprimé etc.. C'est donc un champ conseillé si vous souhaitez mettre en place ce genre de communications.</li></div>"))
                    ->add('elementFormFieldsJson', 'hidden', array('attr' => ['class' => 'gogo-form-builder'])) 
                ->end()
            ->end()
            ->tab('Autres textes et options')
                ->with('Autres textes et options', array('class' => 'col-md-12'))                    
                    ->add('elementFormIntroText', 'textarea'  , 
                        array('required' => false, 'attr' => ['placeholder' => 'Exemple: Attention nous ne référencons pas tel et tel type d\'élements'],
                              'label' => "Texte d'introduction qui apparait en haut du formulaire"))
                    ->add('elementFormValidationText', 'textarea' , 
                        array('required' => false, 'attr' => ['placeholder' => 'Exemple: Je certifie que les informations renseignées dans ce formulaire sont exactes'],
                              'label' => "Label de la checkbox de validation du formulaire (laisser vide pour désactiver)"))
                    ->add('elementFormOwningText', 'textarea' , 
                        array('required' => false, 'attr' => ['placeholder' => 'Exemple: Je suis impliqué.e dans la gestion de la structure décrite'],
                              'label' => "Label pour demander si l'utilisateur est propriétaire de la fiche (laisser vide pour désactiver)"))
                    ->add('elementFormGeocodingHelp', 'textarea' , 
                        array('required' => false,
                              'label' => "Texte d'aide pour la geolocalisation"))
                ->end()
            ->end()
            ->tab('Sémantique')
                ->with('Sémantique', array('class' => 'col-md-12', "description" => "Définir le contexte sémantique des données permet de plus facilement partager les données, afin qu'on puisse proposer un API sous forme de JSON-LD.<br/>Il vous faut aussi définir le type sémantique pour chaque champ que vous voulez partager dans l'onglet Formulaire de cette page."))
                    ->add('elementFormSemanticContext', 'textarea',
                        array('required' => false, 'attr' => ['placeholder' => 'Exemple: https://schema.org'],
                            'label' => "Contexte sémantique des éléments", 'label_attr' => ['title' => "Vous pouvez définir plusieurs contextes sous format de JSON"]))
                    ->add('elementFormSemanticType', 'text',
                        array('required' => false, 'attr' => ['placeholder' => 'Exemple: Place'],
                            'label' => "Type sémantique des éléments", 'label_attr' => ['title' => "Vous pouvez définir plusieurs types séparés par une virgule"]))
                ->end()
            ->end()    
            ;
    }
}