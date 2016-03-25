<?php

namespace Biopen\FournisseurBundle\Repository;

use Doctrine\ORM\EntityRepository;
/**
 * FournisseurRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class FournisseurRepository extends EntityRepository
{
	public function findFromPoint($distance, $point)
	{	 
   $qb = $this->createQueryBuilder('fournisseur');

    $qb = $this->_em->createQueryBuilder()
      ->select('fournisseur as Fournisseur, DISTANCE(fournisseur.latlng, POINT_STR(:point))*100 AS distance')
      ->setParameter('point',$point)
      ->from($this->_entityName, 'fournisseur')
      ->where('fournisseur.valide = 0')
      ->andwhere('DISTANCE(fournisseur.latlng, POINT_STR(:point))*100 < :distance')
      ->setParameter('distance', $distance)
      ->orderBy('distance')  
      ->join('fournisseur.produits', 'fourisseurProduit')
      ->addSelect('fourisseurProduit')
      ->join('fourisseurProduit.produit', 'produit')
      ->addSelect('produit');
    ;

    // Puis on ne retourne que $limit résultats
    //$qb->setMaxResults(10);

    // Enfin, on retourne le résultat
    return $qb
      ->getQuery()
      ->getResult()
      ;
  	}
}
