<?php
/**
 * @Author: Sebastian Castro
 * @Date:   2017-03-28 15:29:03
 * @Last Modified by:   Sebastian Castro
 * @Last Modified time: 2018-07-08 12:52:02
 */

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Route\RouteCollection;

class OptionAdmin extends AbstractAdmin
{
    protected $baseRouteName = 'admin_app_option';
    protected $baseRoutePattern = 'admin_app_option';

    public function createQuery($context = 'list')
    {
        $query = parent::createQuery($context);

        return $query;
    }

    public function getTemplate($name)
    {
        switch ($name) {
         case 'edit': return 'admin/edit/edit_option_category.html.twig';
             break;
         default: return parent::getTemplate($name);
             break;
     }
    }

    protected function configureFormFields(FormMapper $formMapper)
    {
        // prevent circular reference, i.e setting a child as parent
        $dm = $this->getModelManager()->getDocumentManager('App\Document\Configuration');
        $repo = $dm->getRepository('App\Document\Category');
        $parentQuery = $repo->createQueryBuilder()
                          ->field('id')->notIn($this->subject->getAllSubcategoriesIds());

        $formMapper
       ->tab('Principal')
         ->with('Paramètres principaux', ['class' => 'col-xs-12 col-md-6'])
            ->add('name', null, ['required' => true, 'label' => 'Nom'])
            ->add('color', null, ['required' => false, 'label' => 'Couleur', 'attr' => ['class' => 'gogo-color-picker']])
            ->add('icon', null, ['required' => false, 'label' => 'Icone', 'attr' => ['class' => 'gogo-icon-picker']])
            ->add('parent', ModelType::class, [
              'class' => 'App\Document\Category',
              'required' => true,
              'query' => $parentQuery,
              'label' => 'Groupe de Catégorie parent',
              'mapped' => true, ], ['admin_code' => 'admin.categories.lite_hidden'])
         ->end()
         ->with('Paramètres secondaires', ['class' => 'col-xs-12 col-md-6', 'box_class' => 'box'])
            ->add('useIconForMarker', null, ['required' => false, 'label' => "Utiliser l'icone de cette catégorie pour le marqueur", 'label_attr' => ['title' => 'Le marqueur affichera toutes icones de chaque catégorie ayant cette option activée. Les icones seront classées par ordre de selection des catégories dans le formulaire']])
            ->add('useColorForMarker', null, ['required' => false, 'label' => 'Utiliser la couleur de cette catégorie pour le marqueur', 'label_attr' => ['title' => 'Si un élément a plusieurs catégories qui donnent la couleur, on utilise la catégorie de plus bas niveau']])
         ->end()
         ->with('Afficher la catégorie', ['class' => 'col-xs-12 col-md-6', 'box_class' => 'box'])
            ->add('displayInMenu', null, ['required' => false, 'label' => 'Dans le menu'])
            ->add('displayInInfoBar', null, ['required' => false, 'label' => 'Dans la fiche détail'])
            ->add('displayInForm', null, ['required' => false, 'label' => 'Dans le formulaire'])
         ->end()
         // ->with('Sous groupes', array('class' => 'col-xs-12 sub-categories-container'))
         //    ->add('subcategories', 'sonata_type_collection', array('by_reference' => false, 'label_attr'=> ['style'=> 'display:none']),
         //       array(
         //       'edit' => 'inline',
         //       'inline' => 'table',
         //       'admin_code' => 'admin.categories.lite_hidden'
         //       ))
         // ->end()
        ->end()
      ->tab('Configuration avancée')
         ->with('Paramètres secondaires', ['class' => 'col-xs-12 col-md-6', 'box_class' => 'box'])
            ->add('nameShort', null, ['required' => false, 'label' => 'Nom (version courte)', 'label_attr' => ['title' => 'La version courte est utilisée dans le menu, car souvent on manque de place']])
            ->add('customId', null, ['required' => false, 'label' => 'Id personnalisée', 'label_attr' => ['title' => "Lors de l'import d'éléments, on va comparer le nom des catégories de l'élément importé avec le nom des catégorie de votre carte. On va aussi comparer avec les Ids personnalisés"]])
            ->add('softColor', null, ['required' => false, 'label' => 'Couleur adoucie', 'attr' => ['class' => 'gogo-color-picker'], 'label_attr' => ['title' => 'Certaines couleurs convienent bien pour le marqueur (un peu flashy), mais pas trop pour les aplat comme pour le header de la fiche détail. Dans ce cas là, la version "adoucie" est utilisée si ellle a été renseignée']])
            ->add('textHelper', null, ['required' => false, 'label' => "Message d'aide pour décrire rapidement cette catégorie", 'label_attr' => ['title' => 'Il sera affiché dans le menu et dans le formulaire']])
            ->add('url', null, ['required' => false, 'label' => 'Url vers une page externe', 'label_attr' => ['title' => "Dans la fiche détail, une petite icone apâraitra à côté de la catégorie avec un lein vers cette url externe. Cela peut être pas exemple une age d'un wiki pour décrire cette catégorie"]])
            ->add('index', null, ['required' => false, 'label' => 'Position (pour classer les catégories)'])
            ->add('showExpanded', null, ['required' => false, 'label' => 'En position intiale afficher les sous groupes de cette catégorie'])
            ->add('unexpandable', null, ['required' => false, 'label' => 'Ne pas pouvoir reduire cette catégorie', 'label_attr' => ['title' => 'Les sous groupes et sous catégories seront toujours affichés']])
         ->end()

         ->with('Afficher les sous groupes et les sous catégories', ['class' => 'col-xs-12 col-md-6', 'box_class' => 'box'])
            ->add('displayChildrenInMenu', null, ['required' => false, 'label' => 'Dans le menu'])
            ->add('displayChildrenInInfoBar', null, ['required' => false, 'label' => 'Dans la fiche détail'])
            ->add('displayChildrenInForm', null, ['required' => false, 'label' => 'Dans le formulaire'])
         ->end()
      ->end()
      ;
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
          ->addIdentifier('name')
          ->add('_action', 'actions', [
                'actions' => [
                    'edit' => [],
                    'delete' => [],
                    'move' => [
                        'template' => '@PixSortableBehavior/Default/_sort.html.twig',
                    ],
                ],
            ]);
    }

    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->add('move', $this->getRouterIdParameter().'/move/{position}');
    }
}