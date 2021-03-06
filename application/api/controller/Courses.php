<?php

namespace app\api\controller;

use app\admin\model\course\course;
use app\admin\model\course\Category;
use app\common\controller\Api;
use think\Db;

/**
 * 课程接口
 */
class Courses extends Api
{

    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];
    protected $pagesize= 10;
    protected $userid;


    public function _initialize()
    {
        parent::_initialize();
        $userinfo = $this->auth->getUser();
        if($userinfo){
            $this->userid=$userinfo->id;
        }else{
            $this->error('用户信息失效',[],[],403);
        }
    }
    /**
     * 课程分类
     *
     * @ApiTitle    (课程的分类)
     * @ApiMethod   (GET)
     * @ApiRoute    (/api/courses/category)
     * @ApiParams  (name=token, type=string, required=true, description="请求的Token")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
    'code':'1',
    'mesg':'返回成功',
     'token':'a460f6f0b010dccb4560afeaaadfd5d161db044d'
     * })
     */
    public function category()
    {
        //分类数据
        $cate=db('course_category')->order('weigh desc')->select();
        $this->success("返回成功",$cate);
    }

    /**
     * 推荐课程
     *
     * @ApiTitle    (推荐课程)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/courses/recommend)
     * @ApiParams   (name="page", type="integer", required=true, description="页码")
     * @ApiParams  (name=token, type=string, required=true, description="请求的Token")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
    'code':'1',
    'mesg':'返回成功'
     * })
     */
    public function recommend()
    {
        $page =   (int)$this->request->post("page");
        $page = $page?$page-1:0;
        //分类数据
        $data=db('course')->alias('a')
            ->join('course_category b','b.id=a.course_category_id')
            ->join('admin c','c.id=a.admin_id')
            ->order('flag desc,createtime desc')
            ->field('a.*,b.name as type_name,c.nickname as auth')
            ->page($page,$this->pagesize)
            ->select();
//            ->field('id,title,coverimage,content,videfile,views,comments,auth,createtime')

        $allpage=db('course')->alias('a')
                ->join('course_category b','b.id=a.course_category_id')
                ->count();
        $pages['pageCount']=ceil($allpage/$this->pagesize);




        foreach($data as $key=>$val){
            $data[$key]['createtime']=date('Y-m-d',$val['createtime']);
            $data[$key]['content']=$val['coverdesc'];

            //点赞数处理
            $data[$key]['comments'] =$val['comments']+$val['comments_set'];
            $data[$key]['readnum']  =$val['readnum']+$val['readnum_set'];
            $data[$key]['zan']      =$val['zan']+$val['zan_set'];

        }


        $this->success("返回成功",$data,$pages);
    }

    /**
     * 获取分类课程
     *
     * @ApiTitle    (获取某分类的课程)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/courses/course)
     * @ApiParams   (name="type_id", type="integer", required=true, description="分类id")
     * @ApiParams   (name="page", type="integer", required=true, description="页码")
     * @ApiParams   (name="title", type="integer", required=false, description="搜索的标题")
     * @ApiParams  (name=token, type=string, required=true, description="请求的Token")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
    'code':'1',
    'mesg':'返回成功'
     * })
     */
    public function course()
    {
        $page   =   (int)$this->request->post("page");
        $typeid =  (int)$this->request->post("type_id");
        $search = $this->request->request('title');

        if(strlen($search)>100){
            $search=mb_substr($search,0,20);
        }

        if($search) {
            $where = ['name' => ['like', '%' . $search . '%']];

//            $is_history=db('course_search')->where(['user_id'=>$this->userid,'word'=>$search])->find();
//            if(!$is_history){
            //添加搜索历史
            db('course_search')->insert(['user_id' => $this->userid, 'word' => $search, 'createtime' => time()]);
//            }

        }else{
            $where=['course_category_id'=>$typeid];
        };
//            ->field('id,title,coverimage,content,videfile,views,comments,auth,createtime')

        //分类数据
        $data=db('course')->alias('a')
            ->join('admin b','a.admin_id=b.id')
            ->field('a.*,b.nickname as auth')
            ->where($where)->order('createtime desc')
            ->page($page,$this->pagesize)->select();

        $allpage=db('course')->where($where)->count();
        $pages['pageCount']=ceil($allpage/$this->pagesize)?:1;


        foreach($data as $key=>$val){
            $data[$key]['createtime']=date('Y-m-d',$val['createtime']);
            $data[$key]['content']=$val['coverdesc'];
            $data[$key]['comments'] =$val['comments']+$val['comments_set'];

            //点赞处理
            $data[$key]['readnum']  =$val['readnum']+$val['readnum_set'];
            $data[$key]['zan']      =$val['zan']+$val['zan_set'];
            $data[$key]['comments'] =$val['comments']+$val['comments_set'];

        }

        $this->success("返回成功",$data,$pages);
    }



    /**
     * 课程点赞接口
     *
     * @ApiTitle    (对某课程进行点赞)
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/courses/course_zan)
     * @ApiParams   (name="course_id", type="integer", required=true, description="课程id")
     * @ApiParams  (name=token, type=string, required=true, description="请求的Token")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
    'code':'1',
    'mesg':'返回成功'
     * })
     */
    public function course_zan()
    {
        $userid=$this->userid;
        $course_id  =  (int)$this->request->request("course_id");
        if(!$course_id){$this->error('参数不正确');}

        $res=db('course_zan')->where(['user_id'=>$userid,'course_id'=>$course_id])->find();
        if($res){
             db('course_zan')->where(['id'=>$res['id']])->delete();
             db('course')->where('id', $course_id)->setDec('zan');

            $this->success("取消成功");
        }

        $insert['course_id']=$course_id;
        $insert['user_id']=$userid;
        $insert['createtime']=time();
        $res=db('course_zan')->insert($insert);
        if($res){
            //同步新增到course_comment表 赞+1
            db('course')->where('id', $course_id)->setInc('zan');

            $this->success("点赞成功");
        }else{
            $this->error('点赞失败');
        }
    }


    /**
     * 获取课程详情及评论接口
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/courses/detail)
     * @ApiParams   (name="page", type="integer", required=true, description="评论页码")
     * @ApiParams   (name="course_id", type="integer", required=true, description="课程id")
     * @ApiParams  (name=token, type=string, required=true, description="请求的Token")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
    'code':'1',
    'mesg':'返回成功'
     * })
     */
    public function detail()
    {
        $userid=$this->userid;

        $page   =  (int)$this->request->post("page");

        $course_id  =  (int)$this->request->post("course_id");
        if(!$course_id){$this->error('参数不正确');}

        $data=[];
        //初次加载
        if($page===1 || !$page){
            $data['detail'] =db('course')->alias('a')
                            ->where(['a.id'=>$course_id])
                            ->join('admin b','a.admin_id=b.id')
                            ->field('a.*,b.nickname as auth,b.signtext,b.avatar as auth_avatar')
                            ->find();
            $data['detail']['desc']=$data['detail']['coverdesc'];
            $data['detail']['createtime']=date('Y-m-d',$data['detail']['createtime']);
            //
            $data['detail']['comments'] =$data['detail']['comments']+$data['detail']['comments_set'];
            $data['detail']['readnum']  =$data['detail']['readnum']+$data['detail']['readnum_set'];
            $data['detail']['zan']      =$data['detail']['zan']+$data['detail']['zan_set'];



            //同步新增到course浏览数+1
            db('course')->where('id',$course_id)->setInc('readnum');

            //TODO 获取用户信息 判断用户是否点赞
            $collection=db('course_collection')->where(['user_id'=>$userid,'course_id'=>$course_id])->find();
            $data['is_collection']=$collection?1:0;

            $zan=db('course_zan')->where(['user_id'=>$userid,'course_id'=>$course_id])->find();
            $data['is_zan']=$zan?1:0;




            //获取该课程的视频列表
            $node=db('course_nodes')->where(['course_id'=>$course_id])->order('sort asc')->field('id,sort,title,desc,isviewlist')->select();

            $data['detail']['node']=$node;


            //获取是否申请课程
            $audit=db('course_audit')->where(['course_id'=>$course_id,'user_id'=>$userid])->find();
            if(!$audit){
                $data['detail']['is_audit']=0;
            }else{
                switch($audit['checklist']) {
                    case '拒绝':
                        $data['detail']['is_audit'] = 0;
                        break;
                    case '待审核':
                        $data['detail']['is_audit'] = -1;
                        break;
                    case '通过':
                        $data['detail']['is_audit'] = 1;
                        $data['is_can_study']=1;
                        break;
                }
            }

        }

        $where=['course_id'=>$course_id,'pid'=>0];
        //获取第一级评论内容
        $data['comment']= db('course_comment')->alias('a')->join('user','user.id=a.user_id')
                        ->field('user.nickname,avatar,a.*')
                        ->where($where)
                        ->order('a.createtime desc')
                        ->page($page,$this->pagesize)
                        ->select();

        //分页
        $allpage=db('course_comment')->alias('a')->join('user','user.id=a.user_id')->where($where)->count();
        $pages['pageCount']=ceil($allpage/$this->pagesize)?:1;



        //查询该用户对评论点赞的数量
        if($data['comment']){
            foreach($data['comment'] as $key=>$val){
                $data['comment'][$key]['createtime']=date('Y-m-d',$val['createtime']);

                //表情处理
                $data['comment'][$key]['content']=$this->emoji_decode($val['content']);


                $is_zan=db('course_comment_zan')->where(['user_id'=>$this->userid,'comment_id'=>$val['id']])->find();
                if($is_zan){
                    $data['comment'][$key]['is_zan']=1;
                }else{
                    $data['comment'][$key]['is_zan']=0;
                }
                //数据显示
                $data['comment'][$key]['comment_count']=$this->getCommentNum($val['id']);


            }
        }


        $this->success("返回成功",$data,$pages);
    }


    /**
     * 提交评论接口
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/courses/comment)
     * @ApiParams   (name="course_id", type="integer", required=true, description="课程id")
     * @ApiParams   (name="comment_id", type="integer", required=false, description="评论的id")
     * @ApiParams   (name="content", type="string", required=true, description="评论信息")
     * @ApiParams   (name="image", type="file", required=falst, description="图片")
     * @ApiParams  (name=token, type=string, required=true, description="请求的Token")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
    'code':'1',
    'mesg':'返回成功'
     * })
     */
    public function comment()
    {
        $userid=$this->userid;
        $course_id  =  (int)$this->request->post("course_id");
        $comment_id  =  (int)$this->request->request("comment_id");
        if(!$course_id ){$this->error('参数不正确');}

        $content=$this->request->post("content");
        if(!$content){ $this->error('请填写内容');}
        //关键字屏蔽
        $content=$this->wordCheck($content);

        //表情转义
        $content=$this->emoji_encode($content);

//        $is_course=db('course_zan')->where(['user_id'=>$userid,'course_id'=>$course_id])->find();
//        if(!$is_course){
//            $this->success("评论成功");
//        }

        if(!$course_id){
            $this->error('参数错误');
        }
        $insert=[];

        if($_FILES){
            $common=new Common();
            $insert['img']= $common->upload();
        }

        if($comment_id){
            $insert['pid']=$comment_id;
        }

        $insert['course_id']=$course_id;
        $insert['content']=$content;
        $insert['user_id']=$userid;
        $insert['createtime']=time();
        $res=db('course_comment')->insert($insert);

        if($res){
            //同步新增到course表 评论数加1
            db('course')->where('id', $course_id)->setInc('comments');

            $this->success("评论成功");
        }else{
            $this->error('评论失败');
        }
    }


    /**
     * 评论点赞接口
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/courses/comment_zan)
     * @ApiParams   (name="comment_id", type="integer", required=true, description="评论id")
     * @ApiParams  (name=token, type=string, required=true, description="请求的Token")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
    'code':'1',
    'mesg':'返回成功'
     * })
     */
    public function comment_zan()
    {
        $userid=$this->userid;
        $comment_id  =  (int)$this->request->post("comment_id");
        if(!$comment_id){$this->error('参数不正确');}


        $res=db('course_comment_zan')->where(['user_id'=>$userid,'comment_id'=>$comment_id])->find();
        if($res){

            db('course_comment_zan')->where(['id'=>$res['id']])->delete();
            //同步新增到course_comment表 赞-1
            db('course_comment')->where('id', $comment_id)->setDec('zan');

            $this->success("取消成功");
        }

        //无评论，返回成功
        $is_comment=db('course_comment')->where(['id'=>$comment_id])->find();
        if(!$is_comment){
            $this->success("点赞成功");
        }

        $insert['comment_id']=$comment_id;
        $insert['user_id']=$userid;
        $insert['createtime']=time();
        $res=db('course_comment_zan')->insert($insert);
        if($res){
            //同步新增到course_comment表 赞+1
            db('course_comment')->where('id', $comment_id)->setInc('zan');

            $this->success("点赞成功");
        }else{
            $this->error('点赞失败');
        }
    }


    /**
     * 收藏接口
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/courses/collection)
     * @ApiParams   (name="course_id", type="integer", required=true, description="课程id")
     * @ApiParams  (name=token, type=string, required=true, description="请求的Token")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
    'code':'1',
    'mesg':'返回成功'
     * })
     */
    public function collection()
    {
        $userid=$this->userid;

        $course_id= (int)$this->request->post("course_id");

        if(!$course_id ){$this->error('参数不正确');}

        $is_course=db('course')->where(['id'=>$course_id])->find();
        if(!$is_course){
            $this->success("收藏成功");
        }

        $is_have=db('course_collection')->where(['course_id'=>$course_id,'user_id'=>$userid])->find();
        if($is_have){

            db('course_collection')->where(['id'=>$is_have['id']])->delete();
            $this->success("取消成功",[]);
        }

        $insert['course_id']=$course_id;
        $insert['user_id']=$userid;
        $insert['createtime']=time();
        $res=db('course_collection')->insert($insert);
        if(!$res){
            $this->error('收藏失败',[]);
        }
        $this->success("收藏成功",[]);
    }

    /**
     * 获取某条评论详情
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/courses/comment_detail)
     * @ApiParams   (name="comment_id", type="integer", required=true, description="该评论的id")
     * @ApiParams   (name="page", type="integer", required=true, description="分页数")
     * @ApiParams  (name=token, type=string, required=true, description="请求的Token")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
    'code':'1',
    'mesg':'返回成功'
     * })
     */

    public function comment_detail()
    {
        $comment_id= (int)$this->request->request("comment_id");
        if(!$comment_id){$this->error('参数不正确');}

        $page= (int)$this->request->request("page");

        $allPage=db('course_comment')->alias('a')
                ->join('user u','u.id=a.user_id')
                ->where(['pid'=>$comment_id])
                ->count();
        //获取当前数据的详情
        $detail=db('course_comment')->alias('a')
            ->join('user u','u.id=a.user_id')
            ->where(['a.id'=>$comment_id])
            ->field('u.nickname,avatar,a.*')
            ->find();
        $detail['createtime']=date('Y-m-d',$detail['createtime']);

        //表情处理
        $detail['content']=$this->emoji_decode($detail['content']);

        //获取用户是否点赞
        $zan=db('course_comment_zan')->where(['user_id'=>$this->userid,'comment_id'=>$comment_id])->find();
        $detail['is_zan']=($zan?1:0);



        $pages['page_count']=ceil($allPage/$this->pagesize)?:1;
        $detail['children']=$this->commentTree($comment_id,true,$page);

        $this->success('获取成功',$detail,$pages);
    }


    protected function commentTree($id,$is_first=false,$page=1)
    {
        $tree=[];
        $query=db('course_comment')->alias('a')
                ->join('user u','u.id=a.user_id')
                ->where(['pid'=>$id]);
        if($is_first) {
            $query->page($page,$this->pagesize);
        }

        $data= $query->field('u.nickname,avatar,a.*')->select();


        if($data){
            //子类存在数据
            foreach($data as $key=>$val){
                $zan=db('course_comment_zan')->where(['user_id'=>$this->userid,'comment_id'=>$val['id']])->find();
                $val['is_zan']=($zan?1:0);
                $val['createtime']=date('Y-m-d',$val['createtime']);
                $val['content']=$this->emoji_decode($val['content']);
                $val['children']=$this->commentTree($val['id']);
                $tree[]=$val;
            }
        }
        return $tree;
    }


    /**
     * 申请课程接口
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/courses/audit)
     * @ApiParams   (name="course_id", type="integer", required=true, description="课程的id")
     * @ApiParams  (name=token, type=string, required=true, description="请求的Token")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
    'code':'1',
    'mesg':'返回成功'
     * })
     */

    public function audit()
    {
        $course_id= (int)$this->request->request("course_id");
        if(!$course_id ){$this->error('参数不正确');}

        $userid=$this->userid;

        $is_have=db('course_audit')->where(['course_id'=>$course_id,'user_id'=>$userid])->find();
        if($is_have){
            $this->success("已提交申请",[]);
        }

        $insert['user_id']=$userid;
        $insert['course_id']=$course_id;
        $insert['createtime']=time();
        $res=db('course_audit')->insert($insert);
        if($res){
            $this->success("提交成功",[]);
        }else{
            $this->error("提交失败",[]);
        }

    }


    /**
     * 获取课程的课时详情接口
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/courses/nodes_detail)
     * @ApiParams   (name="nodes_id", type="integer", required=true, description="课时的id")
     * @ApiParams  (name=token, type=string, required=true, description="请求的Token")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
    'code':'1',
    'mesg':'返回成功'
     * })
     */

    public function nodes_detail()
    {
        $nodes_id= (int)$this->request->request("nodes_id");
        if(!$nodes_id ){$this->error('参数不正确');}

        $userid=$this->userid;

        $is_views=db('course_nodes')->where(['id'=>$nodes_id])->find();
        if(!$is_views){
            $this->success("获取成功",[]);
        }
        //查看该课时是否为可体验
        if($is_views['isviewlist']=='不可体验'){

            //不是体验课程判断是否申请了并且通过了申请
            $is_check=db('course_audit')->where(['user_id'=>$userid,'checklist'=>'通过'])->find();
            //通过了就返回课程内容
            if(!$is_check){
                $this->error('请先申请课程');
            }

        }

        $detail=db('course_nodes')->where(['id'=>$nodes_id])->find();
        //获取课程的内容
        $course=db('course')->alias('a')
            ->join('admin b','a.admin_id=b.id')
            ->where(['a.id'=>$detail['course_id']])
            ->field('a.name,a.createtime,a.id,b.avatar')
            ->find();
        $course['createtime']=date('Y-m-d',$course['createtime']);

        $data['detail']=$detail;
        $data['course']=$course;
        $this->success('获取成功',$data);


    }


    /**
     * 获取用户课程搜索历史[10条]
     * @ApiMethod   (GET)
     * @ApiRoute    (/api/courses/search_keywords)
     * @ApiParams  (name=token, type=string, required=true, description="请求的Token")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'user_id':'int','user_name':'string','profile':{'email':'string','age':'integer'}}", description="扩展数据返回")
     * @ApiReturn   ({
    'code':'1',
    'mesg':'返回成功'
      })
     */
    public function search_keywords()
    {
        $userid=$this->userid;
        $list=db('course_search')->where(['user_id'=>$userid])->group('word')->limit(0,10)->field('word')->select();
        $this->success('获取成功',$list);
    }


    /**
     * 清空搜索历史记录
     * @ApiMethod   (POST)
     * @ApiRoute    (/api/courses/delete_keywords)
     * @ApiParams  (name=token, type=string, required=true, description="请求的Token")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     */
    public function delete_keywords()
    {
        $userid=$this->userid;
        $list=db('course_search')->where(['user_id'=>$userid])->delete();
        if($list){
            $this->success('删除成功',$list);
        }
    }


    protected function getCommentNum($pid,$num=0){

        $number=db('course_comment')->where(['pid'=>$pid])->count();
        $num+=$number;
        unset($number);
        $list=db('course_comment')->where(['pid'=>$pid])->select();
        if(is_array($list)){
            foreach($list as $key=>$val){
                $child=db('course_comment')->where(['pid'=>$val['id']])->find();
                if($child){
                    $num+= $this->getCommentNum($val['id'],$num);
                }
            }
        }

        return $num;
    }



}
