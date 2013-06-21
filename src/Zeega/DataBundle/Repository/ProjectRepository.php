<?php

namespace Zeega\DataBundle\Repository;

use Doctrine\ODM\MongoDB\DocumentRepository;
use Zeega\DataBundle\Document\Project;
use JMS\DiExtraBundle\Annotation as DI;

class ProjectRepository extends DocumentRepository
{
    private $idGenerator;

    private function createQueryBuilderWithId($id, $equal = True) 
    {
        if(strlen($id) == 24) {
            if ( $equal ) {
                return $this->createQueryBuilder('Project')->field('id')->equals($id);   
            } else {
                return $this->createQueryBuilder('Project')->field('id')->notEqual($id);
            }
        } else {
            if ( $equal ) {
                return $this->createQueryBuilder('Project')->field('projectId')->equals($id);   
            } else {
                return $this->createQueryBuilder('Project')->field('projectId')->notEqual($id);
            }
        }
    }

    /**
     * @DI\InjectParams({
     *     "generator" = @DI\Inject("zeega_id")
     * })
     */
    public function setPublicIdGenerator($generator) {
        $this->idGenerator = $generator;
    }

    public function findOneById($id)
    {
        $qb = $this->createQueryBuilder('Project');

        if(strlen($id) == 24) {
            $project = parent::findOneById($id);
        } else {
            $project = parent::findOneBy(array("publicId" => $id));
        }
               
        return $project;
    }

    public function findProjectsByUser($userId,$limit = null,$published = null)
    {
        $qb = $this->createQueryBuilder('Project')
            ->select('user','id','public_id', 'title','uri', 'cover_image', 'authors', 'date_created', 'editable','tags')
            ->eagerCursor(true)
            ->field('user.id')->equals($userId)
            ->field('enabled')->equals(true)
            ->sort('date_created','DESC');
        
        if(null !== $published) {
            $qb->field('published')->equals($published);
        }

        if(null !== $limit) {
            $qb->limit($limit);
        }

        return $qb->getQuery()->execute();
    }

    public function findProjectsCountByUser($userId)
    {
        return $this->createQueryBuilder('Project')
            ->field('user.id')->equals($userId)
            ->field('enabled')->equals(true)
            ->eagerCursor(true)
            ->getQuery()->execute()->count();
    }


    // Place holder for related Zeegas
    public function findRelated( $id = null, $query = null )
    {
        $qb = $this->createQueryBuilderWithId($id, False);
        $qb->select('user','id','title','uri', 'cover_image', 'authors', 'date_created', 'tags')
            ->field('user')->prime(true)
            ->eagerCursor(true)
            ->limit( 2 )
            ->skip( rand(0, 8) )
            ->sort('date_created','DESC');

        $qb->field('tags.name')->equals("featured");
        
        return $qb->getQuery()->execute();
    }

    public function findByQuery($query)
    {
        if ( isset($query["text"]) ) {
            $command = array(
                "text" => "Project", 
                "search" => $query["text"],
                "project" => array("id"=>1,'public_id'=>1, 'user'=>1,'title'=>1,'uri'=>1, 'cover_image'=>1, 'authors'=>1, 'date_created'=>1, 'editable'=>1, 'tags'=>1)
                );
            $filter = array();

            if ( isset($query["user"]) ) {
                $filter["user"] = new \MongoId($query["user"]);
            }

            $command["filter"] = $filter;

            $connection = $this->getDocumentManager()->getConnection();
            $database = $this->getDocumentManager()->getConfiguration()->getDefaultDB();
            $results = $connection->selectDatabase($database)->command($command);
            $projects = array();

            if ( isset($results) && isset($results["results"]) ) {

                foreach($results["results"] as $result) {            
                    $project = new Project();
                    $this->getDocumentManager()->getHydratorFactory()->hydrate($project, $result["obj"]);
                    array_push($projects,$project);
                }    
            }            
            
            return $projects;
        } else {        
            $qb = $this->createQueryBuilder('Project')
                        ->select('user','id','public_id', 'title','uri', 'cover_image', 'authors', 'date_created', 'tags')
                        ->field('user')->prime(true)
                        ->eagerCursor(true)
                        ->limit($query['limit'])
                        ->skip($query['limit'] * $query['page']);
                        

            if (isset($query["tags"])) {
                $qb->field('tags.name')->equals($query["tags"]);
                $qb->sort('date_updated','DESC');
            }

            if (isset($query["user"])) {
                $qb->field('user.id')->equals($query["user"]);
            }

            if(isset($query['sort'])) {
                $sort = $query['sort'];
                if($sort == 'date-updated-desc') {
                    $qb->sort('date_updated','DESC');
                } else if($sort == 'date-updated-asc') {
                    $qb->sort('date_updated','ASC');
                } else {
                    $qb->sort('date_created','DESC');
                }
            } else {
                $qb->sort('date_created','DESC');
            }

            return $qb->getQuery()->execute();
        }
    }

    public function findProjectFrame($projectId, $frameId) {
        $qb = $this->createQueryBuilderWithId($projectId);
        $project = $qb->eagerCursor(true)
            ->select('frames')
            ->getQuery()
            ->getSingleResult();

        if ( !isset($project) || !$project instanceof MongoProject) {
            return null;
        } 

        $frames = $project->getFrames();
        $frame = $project->getFrames()->filter(
            function($fram) use ($frameId){
                return $fram->getId() == $frameId;
            }
        )->first();
        
        if ( !isset($frame) || !$frame instanceof MongoFrame) {
            return null;  
        } else {
            return $frame;
        }
    }

    public function findProjectFrameWithLayers($projectId, $frameId) {
        $qb = $this->createQueryBuilderWithId($projectId);

        $project = $qb->createQueryBuilder('ZeegaDataBundle:Project')
            ->select('frames','layers')
            ->eagerCursor(true)
            ->getQuery()
            ->getSingleResult();

        if ( !isset($project) ) {
            return "null";
        } 
        
        $frame = $project->getFrames()->filter(
            function($fram) use ($frameId){
                return $fram->getId() == $frameId;
            }
        )->first();
        
        if ( !isset($frame) ) {
            return null;  
        } 

        $layersIds = $frame->getLayers();

        if ( isset($layersIds) && is_array($layersIds) ) {
            $layers = $project->getLayers()->filter(
                function($layr) use ($layersIds){
                    return in_array($layr->getId(), $layersIds);
                }
            );

            if ( isset($layers) ) {
                return array("frame"=> $frame, "layers" => $layers);
            }
        }

        return array("frame"=> $frame, "layers" => array());
    }


    public function findProjectsCountByDates($dateBegin, $dateEnd )
    {
        $qb = $this->createQueryBuilder('Project');
        $qb ->field('cover_image')->notEqual( null )
            ->field('cover_image')->notEqual( null )
            ->eagerCursor(true)
            ->field('date_created')->range( $dateBegin, $dateEnd );
                      
        return $qb->getQuery()->execute()->count();
    }
    // users with at least one zeega
    // users with more than one zeega

    public function findActiveUsersCountByDates($dateBegin, $dateEnd, $new = null, $numZeegas = 0.0, $datePrevious = null )
    {
        $qb = $this->createQueryBuilder('Project')
            ->field('dateCreated')->gte($dateBegin)
            ->field('dateCreated')->lte($dateEnd)
            ->field('published')->equals(true)          
            ->map('function() { 
                emit(this.user.$id, 1); 
            }')
            ->reduce('function(k, vals) {
                var sum = 0;
                for (var i in vals) {
                    sum += vals[i];
                }
                return sum;
            }');

        if (null !== $new) {
             $qb->field('user.lastLogin')->gte($dateBegin)
                ->field('user.lastLogin')->lte($dateEnd);
        }

        $query = $qb->getQuery();
        $projects = $query->execute();
        $count = 0;
        foreach($projects as $project) {
            if ((double)$project["value"] > $numZeegas) {                
                $count = $count + 1;
            }
        }

        return $count;
    }
}
