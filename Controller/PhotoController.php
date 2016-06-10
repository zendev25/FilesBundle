<?php

namespace ZEN\FilesBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ZEN\FilesBundle\Entity\Photo;
use ZEN\FilesBundle\Form\PhotoType;
use ReflectionClass;

class PhotoController extends Controller {

    //retourne la nombre maximum de photo autorisé à l'upload
    public function getLimitPhoto($parentGallery){
        
        return $this->container->getParameter('zen_files.maxPhotoUpload');
        
    }
    
    //Estce que les galleries enfants sont activés ou non
    //retourne boolean 
    public function isChildGalleryAllow($parentGallery){
        
        return $this->container->getParameter('zen_files.allowChildGallery');
        
    }
    
    
    /**
     * 
     * @param Request $request
     * @return Response|RedirectResponse
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function manageAction(Request $request, $register=false, $page = 0) {
        
        //Récupère la gallerie principal (host ou hotel)
        $parentGallery = $this->getParentGallery();
        
        //pas de gallerie principal : retourne une erreur
        if (null == $parentGallery)
        {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Pas de Host/Hotel');
        }
        
        
        $em = $this->getDoctrine()->getManager();
        
        //récupère le path vers le dossier web
        $webDir = $this->get('kernel')->getRootDir() . '/../web';
        //on le passe en param à l'entité photo
        $photo = new Photo($webDir);

        //Instancie entité photo à panret_gallery
        $photo->setParentGallery($parentGallery);

        //Récupère les galleries enfants
        if($this->isChildGalleryAllow($parentGallery)){
            $childsGallery = $this->getChildsGallery($parentGallery->getId());
        }else{
            $childsGallery = false;
        }
        
        //création du formulaire de photo de l'établissement
        //utilisation de createnameBuilder pour assigner au formulaire et avoir plusieurs formulaires sur la page
        $multiForm['parent-gallery'] = $this->get('form.factory')
                ->createNamedBuilder('parent-gallery', new PhotoType(0), $photo, array(
                    'attr' => array('data-childgaleryinparentgalery' => $this->container->getParameter('zen_files.childGaleryInParentGalery'))
                ))
                ->getForm();
        
        //pour tout les galleries enfants, on créer un formulaire correspondant
        if($childsGallery){
            foreach ($childsGallery as $childGallery) {
                $multiForm['child-gallery-' . $childGallery->getId()] = $this->get('form.factory')->createNamedBuilder('child-gallery-' . $childGallery->getId(), new PhotoType($childGallery->getId()), $photo)->getForm();
            }
        }

//        var_dump(count($parentGallery->getPhotos()));
        //Si un des formulaire à été posté
        if ('POST' === $request->getMethod()) {

            if(count($parentGallery->getPhotos()) < $this->getLimitPhoto($parentGallery)){
                //récupère le nom du formulaire posté
                $formName = $request->request->keys()[0];

                $multiForm[$formName]->handleRequest($request);



                //vérifie le formualire
                if ($multiForm[$formName]->isValid()) {

                    //Si le le champ hallId est rensigné
                    if ($multiForm[$formName]->get('childGalleryId')->getData()) {
                        //on récupère l'instance childGallery correspondant au childGalleryId posté
                        $childGallery = $em->getRepository($this->container->getParameter('zen_files.model_class_child_gallery')['model_class_child_gallery'])->find($multiForm[$formName]->get('childGalleryId')->getData());
                        //on lie l'instance photo à childGallery
                        $photo->setChildGallery($childGallery);
                    }

                    //L'entité photo contient un prePersist et un postPersist qui permet de s'assurer que l'image à bien été uploader et enregistré en db
                    $em->persist($photo);

                    //sauvegarde en base
                    $em->flush();


                    //la requete était en ajax
                    //retourne un json au js, pour qu'il affiche une miniature de l'image
                    if ($request->isXmlHttpRequest()) {

                        $response = new Response();
                        $output = array('success' => true, 'element' => $this->renderView('ZENFilesBundle::block-photo-thumb.html.twig', array('photo' => $photo)));
                        $response->headers->set('Content-Type', 'application/json');
                        $response->setContent(json_encode($output));
                        return $response;

                    } else {

                        $url = $this->get('router')->generate('li_bo_photos');
                        return new RedirectResponse($url);
                    }

                    //le formulaire n' pas été validé
                } else {

                    //Appel les messages d'erreur du form
                    $message = $this->get('misc')->getAllErrorMessages($multiForm[$formName]);

                    //on retourne au js les erreurs
                    if ($request->isXmlHttpRequest()) {
                        $response = new Response();
                        $output = array('success' => false, 'errors' => $message);
                        $response->headers->set('Content-Type', 'application/json');
                        $response->setContent(json_encode($output));

                        return $response;
                    }
                }
            }else{
                //Appel les messages d'erreur du form
                $message = array($this->get('translator')->trans('maxPhotoUpload', array(), 'notice'));

                //on retourne au js les erreurs
                if ($request->isXmlHttpRequest()) {
                    $response = new Response();
                    $output = array('success' => false, 'errors' => $message);
                    $response->headers->set('Content-Type', 'application/json');
                    $response->setContent(json_encode($output));

                    return $response;
                }
            }
        }

        //appel la méthode createView sur tous les formulaires
        foreach ($multiForm as $k => $form) {
            $renderReturn['forms'][$k] = $form->createView();
        }


        $renderReturn['parentGallery'] = $parentGallery;
        if($childsGallery){
            for ($i = 0; $i < count($childsGallery); $i++) {
                $renderReturn['childsGallery']['child-gallery-' . $childsGallery[$i]->getId()] = $childsGallery[$i];
            }
        }
        
        $renderReturn['childGaleryInParentGalery'] = $this->container->getParameter('zen_files.childGaleryInParentGalery');
        $renderReturn['parentGallery'] = $parentGallery;
        $renderReturn['maxPhotoUpload'] = $this->getLimitPhoto($parentGallery);
            
        if($register){
            $renderReturn['page'] = $page;
            
            return $this->render('ZENFilesBundle::layout-register-manage-photo.html.twig', $renderReturn);
        }else{

            return $this->render('ZENFilesBundle::layout-manage-photo.html.twig', $renderReturn);
        }
        
        
    }


    //supprimer une photo
    public function deleteAction($id, Request $request) {


        $repository = $this->getDoctrine()->getRepository('ZENFilesBundle:Photo');

        $em = $this->getDoctrine()->getManager();

        //Récupère l'entité parentGallery
        $parentGallery = $this->getParentGallery();

        //Instancie photo
        $photo = $repository->find($id);

        //Vérifie que la photo apparetient bien à l'hotel
        if ($photo->getParentGallery() == $parentGallery) {
            $em->remove($photo);
            $em->flush();

            $response = new Response();
            $output = array('success' => true, 'success' => array('Votre image a bien été supprimé'));
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent(json_encode($output));
            return $response;
        }
        
        
    }
    
    
    
    
    //Retourne la gallerie principal, (host ou hotel) correspondant à l'id en paramètre
    protected function getParentGallery() {
        $em = $this->getDoctrine()->getManager();
        //récupère entité lié a parent_gallery (cf config.yml)
        $model_gallery = $this->container->getParameter('zen_files.model_class_parent_gallery')['model_class_parent_gallery'];
        
        
        
        //récupère le nom de l'entité parentGallery
        $class = new ReflectionClass($model_gallery);
        $shortName = $class->getShortName();
        $getCustom = 'get' . ucfirst($shortName).'ByUser';

        $parentGallery = $this->container->get('li.corebundle.hotel')->$getCustom();
        
        return $parentGallery;
   
    }
    
    //retourne les galleries enfants (hall ou room) 
    //Les images présentes dans ces galleries sont automatiquement ajoutés à la gallerie parent
    protected function getChildsGallery($id_parent_gallery){
        $em = $this->getDoctrine()->getManager();
        
        //récupère entité lié a parent_gallery (cf config.yml)
        $model_parent_gallery = $this->container->getParameter('zen_files.model_class_parent_gallery')['model_class_parent_gallery'];
        
        //récupère entité lié a child_gallery (cf config.yml)
        $model_child_gallery = $this->container->getParameter('zen_files.model_class_child_gallery')['model_class_child_gallery'];
        
        //récupère les informations de l'entité correspodnant à child_gallery
        $metaData = $em->getClassMetadata($model_child_gallery);
        
        //récupère les associations de l'entité child_gallery
        $associationMappings = $metaData->getAssociationMappings();
        
        //parcours les associations
        foreach ($associationMappings as $assoc) {
            //Recherche la correpondance l'association entre child_gallery et parent_gallery (Hall -> Host) ou (Room -> Hotel)
            if ($assoc['targetEntity'] == $model_parent_gallery) {
                //écris la fonction à utilisé findBy (Host ou Hotel)
                $findByCustom = 'findBy' . ucfirst($assoc['fieldName']);
            }
        }
        
        //Appel le repository de child_gallery
        $repository = $this->getDoctrine()->getRepository($metaData->name);
     
        //Excute la fonction créer plus haut, pour récupèrer les child_gallery en correpondance avec parent_gallery
        $childsGallery = $repository->$findByCustom($id_parent_gallery);
        
        return $childsGallery;
        
    }
}
