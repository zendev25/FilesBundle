<?php

namespace ZEN\FilesBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Photo
 *
 * @ORM\Table(options={"engine"="MyISAM"})
 * @ORM\Entity(repositoryClass="ZEN\FilesBundle\Entity\PhotoRepository")
 * @ORM\HasLifecycleCallbacks
 */
class Photo {

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(name="path", type="text")
     */
    private $path;

    /**
     * @var string
     * @ORM\Column(name="alt", type="string", length=255)
     */
    private $alt;

    /**
     * @var string
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var integer
     * @Assert\Type(type="integer")
     * @Assert\Length(min = "1", max = "1")
     * @ORM\Column(name="coverPhoto", type="smallint", nullable=true)
     */
    private $coverPhoto;

    /**
     * @var integer
     * @Assert\Type(type="integer")
     * @Assert\GreaterThanOrEqual(value = 0)
     * @ORM\Column(name="deleted", type="smallint", nullable=true)
     */
    private $deleted = 0;

    
    /**
     * @ORM\ManyToOne(targetEntity="ZEN\FilesBundle\Model\ParentGalleryInterface", inversedBy="photos")
     * @var ParentGalleryInterface
     */
    protected $parentGallery;
    
    /**
     * @ORM\ManyToOne(targetEntity="ZEN\FilesBundle\Model\ChildGalleryInterface", inversedBy="photos")
     * @var ChildGalleryInterface
     */
    protected $childGallery;

    /**
     * @Assert\Image(
     *     minWidth = 1200,
     *     minWidthMessage = "Votre image doit être supérieur à 1200px de large",
     *     maxWidth = 2000,
     *     maxWidthMessage = "Votre image doit être inférieur à 2000px de large",
     *     minHeight = 700,
     *     minHeightMessage = "Votre image doit être supérieur à 700px de haut",
     *     maxHeight = 1400,
     *     maxHeightMessage = "Votre image doit être inférieur à 1400px de haut"
     * )
     */
    private $file;
    // On ajoute cet attribut pour y stocker le nom du fichier temporairement
    private $tempFilename;

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set path
     *
     * @param string $path
     * @return Photo
     */
    public function setPath($path) {
        $this->path = $path;

        return $this;
    }

    /**
     * Get path
     *
     * @return string 
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * Set status
     *
     * @param integer $status
     * @return Photo
     */
    public function setStatus($status) {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return integer 
     */
    public function getStatus() {
        return $this->status;
    }

    
    public function getFile() {
        return $this->file;
    }

    /**
     * Set coverPhoto
     *
     * @param integer $coverPhoto
     * @return Photo
     */
    public function setCoverPhoto($coverPhoto) {
        $this->coverPhoto = $coverPhoto;

        return $this;
    }

    /**
     * Get coverPhoto
     *
     * @return integer 
     */
    public function getCoverPhoto() {
        return $this->coverPhoto;
    }

    /**
     * Get alt
     *
     * @return string 
     */
    public function getAlt() {
        return $this->alt;
    }

    /**
     * Set alt
     *
     * @param string $alt
     * @return Photo
     */
    public function setAlt($alt) {
        $this->alt = $alt;

        return $this;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Photo
     */
    public function setName($name) {
        $this->name = $name;

        return $this;
    }

    /**
     * Get alt
     *
     * @return string 
     */
    public function getName() {
        return $this->name;
    }

    // On modifie le setter de File, pour prendre en compte l'upload d'un fichier lorsqu'il en existe déjà un autre
    public function setFile(UploadedFile $file) {

        $this->file = $file;

        // On vérifie si on avait déjà un fichier pour cette entité
        if (null !== $this->path) {

            // On sauvegarde l'extension du fichier pour le supprimer plus tard
            $this->tempFilename = $this->path;


            // On réinitialise les valeurs des attributs path et name
            $this->path = null;
            $this->alt = null;
        }
    }

    /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    public function preUpload() {

        // Si jamais il n'y a pas de fichier (champ facultatif)
        if (null === $this->file) {
            return;
        }
        
        //vérifie le type de fichier 
        
        
//        var_dump($this->file->getMimeType());
//
//        die();
        // Le nom du fichier est son id, on doit juste stocker également son extension
        // Pour faire propre, on devrait renommer cet attribut en « extension », plutôt que « path »
        $this->name = $this->file->getClientSize() . time() . '.' . $this->file->guessExtension();
        $this->path = $this->getUploadDir() . '/' . $this->name;

        // Et on génère l'attribut name de la balise <img>, à la valeur du nom du fichier sur le PC de l'internaute
        $this->alt = $this->file->getClientOriginalName();
    }

    /**
     * @ORM\PostPersist()
     * @ORM\PostUpdate()
     */
    public function upload() {


        // Si jamais il n'y a pas de fichier (champ facultatif)
        if (null === $this->file) {
            return;
        }

        // Si on avait un ancien fichier, on le supprime
        if (null !== $this->tempFilename) {

            $oldFile = $this->getUploadRootDir() . '/' . $this->name;

            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        
        // On déplace le fichier envoyé dans le répertoire de notre choix
        $this->file->move(
            $this->getUploadRootDir(), // Le répertoire de destination
            $this->name   // Le nom du fichier à créer
        );
        chmod($this->getBaseDir(), 0755);
        chmod($this->getUploadRootDir(), 0755);
        chmod($this->getUploadRootDir().'/'.$this->name, 0644);
        
//        chmod($this->getUploadRootDir().'/'.$this->name, 0644);
    }

    /**
     * @ORM\PreRemove()
     */
    public function preRemoveUpload() {
        // On sauvegarde temporairement le nom du fichier, car il est stocké en db
        $this->tempFilename = $this->path;
    }

    /**
     * @ORM\PostRemove()
     */
    public function removeUpload() {
        // En PostRemove, on n'a pas accès à la db, on utilise notre nom sauvegardé
        if (file_exists($this->tempFilename)) {

            // On supprime le fichier
            unlink($this->tempFilename);
        }
    }

    public function getUploadDir() {

        $dir = $this->getBaseDir(). '/default';
        if (!is_dir($dir))
        {
            mkdir($dir,755);
        }
        // On retourne le chemin relatif vers l'image pour un navigateur
        return $dir;
    }
    
    private function getBaseDir()
    {
        $parentGallery = $this->getParentGallery();
        $dir = 'uploads/hotel/' . $parentGallery->getSlug();
        if (!is_dir($dir))
        {
            mkdir($dir,755);
        }
        return $dir;
    }

    protected function getUploadRootDir() {

        // On retourne le chemin relatif vers l'image pour notre code PHP
        return __DIR__ . '/../../../../web/' . $this->getUploadDir();
    }


    /**
     * Set deleted
     *
     * @param integer $deleted
     * @return Photo
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * Get deleted
     *
     * @return integer 
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

   


    /**
     * Set parentGallery
     *
     * @param \ZEN\FilesBundle\Model\ParentGalleryInterface $parentGallery
     * @return Photo
     */
    public function setParentGallery(\ZEN\FilesBundle\Model\ParentGalleryInterface $parentGallery = null)
    {
        $this->parentGallery = $parentGallery;

        return $this;
    }

    /**
     * Get parentGallery
     *
     * @return \ZEN\FilesBundle\Model\ParentGalleryInterface 
     */
    public function getParentGallery()
    {
        return $this->parentGallery;
    }

    /**
     * Set childGallery
     *
     * @param \ZEN\FilesBundle\Model\ChildGalleryInterface $childGallery
     * @return Photo
     */
    public function setChildGallery(\ZEN\FilesBundle\Model\ChildGalleryInterface $childGallery = null)
    {
        $this->childGallery = $childGallery;

        return $this;
    }

    /**
     * Get childGallery
     *
     * @return \ZEN\FilesBundle\Model\ChildGalleryInterface 
     */
    public function getChildGallery()
    {
        return $this->childGallery;
    }
}
