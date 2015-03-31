<?php  if (!defined('APPLICATION')) exit();
$PluginInfo['KarmaBankPostTypeCategory'] = array(
    'Name' => 'KarmaBank Post Type Category',
    'Description' => 'Extends KarmaBank to set rule that cross reference different post types (Depending on Discussion Type), per category',
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'RequiredPlugins' => array('KarmaBank' => '0.9.7.0b'),
    'Version' => '0.1.1b',
    'Author' => "Paul Thomas",
    'AuthorEmail' => 'dt01pqt_pt@yahoo.com'
);

class KarmaBankPostTypeCategory extends Gdn_Plugin {
    
    protected $PostTypes = array();
    
    function __construct(){
        $this->PostTypes['Discussion'] = array('Discussion' => 'Discussion', 'Comment' => 'Comment');
        if(C('EnabledPlugins.QnA'))
            $this->PostTypes['Question']= array('Discussion' => 'Question', 'Comment' => 'Answer');
        parent::__construct();
        $this->FireEvent('AddPostTypes');
    }
    
    public function KarmaBank_KarmaBankMetaMap_Handler($Sender, $Args){
        $CategoryModel = new CategoryModel();
        
        $Categories = $CategoryModel->GetAll();
        
        $CategoryNames = array('default' => 'Default');
        
        foreach($Categories As $Category){
            if($Category->CategoryID>0)
                $CategoryNames[$Category->Name] = str_replace(' ', '', ucwords(str_replace('-', ' ', $Category->UrlCode)));
        }
        
        foreach($this->PostTypes As $PostTypeName => $PostType){
            foreach($PostType As $PostTerm){
                foreach($CategoryNames As $CategoryRealName => $CategoryName){
                    $Sender->AddMeta(
                        "{$PostTypeName}{$PostTerm}{$CategoryName}", 
                        "Counts on new {$PostTerm} post of {$PostTypeName} in {$CategoryRealName} category"
                    );
                }
            }
        }
    }
    
    protected function UpdateCounts($PostType, $Args){
        $UserID = GetValue('InsertUserID', $Args, GetValueR('FormPostValues.InsertUserID', $Args));
        $DiscussionType = GetValue('Type',$Args, GetValueR('Discussion.Type', $Args, 'Discussion'));
        $PostTerm = GetValueR("{$DiscussionType}.{$PostType}", $this->PostTypes, $this->PostTypes['Discussion']);
        $Category = CategoryModel::Categories(GetValue('CategoryID',$Args,GetValueR('Discussion.CategoryID', $Args)));
        $CategoryName = str_replace(' ', '', ucwords(str_replace('-', ' ', Getvalue('UrlCode',$Category))));
        $PostType = $DiscussionType;
        if($PostType && $PostTerm && $CategoryName){
            $KarmaRules = new KarmaRulesModel();
            $Rules = $KarmaRules->GetRules();
            $HasRule = FALSE;
            $HasDefaultRule = FALSE;
            foreach($Rules As $Rule){
                if($Rule->Condition == "{$PostType}{$PostTerm}{$CategoryName}"){
                    $HasRule = TRUE;
                }
                if($Rule->Condition == "{$PostType}{$PostTerm}Default"){
                    $HasDefaultRule = TRUE;
                }
            }
            
            if($HasRule || $HasDefaultRule){
                $Counts = Gdn::UserModel()->GetMeta($UserID, "{$PostType}{$PostTerm}%");
                
                if(!$Counts){
                    $Counts = array();
                }
                if($HasRule){
                    if(!GetValue("{$PostType}{$PostTerm}{$CategoryName}",$Counts))
                        $Counts["{$PostType}{$PostTerm}{$CategoryName}"] = 0;
                    $Counts["{$PostType}{$PostTerm}{$CategoryName}"]=intval($Counts["{$PostType}{$PostTerm}{$CategoryName}"])+1;
                }else if($HasDefaultRule){
                    if(!GetValue("{$PostType}{$PostTerm}Default",$Counts))
                        $Counts["{$PostType}{$PostTerm}Default"] = 0;
                    $Counts["{$PostType}{$PostTerm}Default"]=intval($Counts["{$PostType}{$PostTerm}Default"])+1;
                }

                Gdn::UserModel()->SetMeta($UserID, $Counts);
                
            }
        }
        
    }
    
    public function DiscussionModel_BeforeSaveDiscussion_Handler($Sender, $Args){
        if(GetValue('Insert',$Args))
            $this->UpdateCounts('Discussion', GetValue('FormPostValues',$Args,array()));
    }
    
    public function CommentModel_BeforeUpdateCommentCount_Handler($Sender, $Args){
        if(!GetValue('FormPostValues.CommentID',$Args))
            $this->UpdateCounts('Comment', $Args);
    }
    
    public function Base_BeforeControllerMethod_Handler($Sender) {
        if(!Gdn::PluginManager()->GetPluginInstance('KarmaBank')->IsEnabled())
          return;
        if(!Gdn::Session()->IsValid()) return;
         
    }
    
}
