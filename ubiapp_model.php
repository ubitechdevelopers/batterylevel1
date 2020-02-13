<?php

/**
 * Ubiapp Modal
 *
 * Handles the Mobile app services of HRM
 */


class UbiappModel
{
    /**
     * Constructor, expects a Database connection
     * @param Database $db The Database object
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    /**
     * Login process (for DEFAULT user accounts).
     * Users who login with Facebook etc. are handled with loginWithFacebook()
     * @return bool success state
     */
    public function checklogin($request)
    {
    	$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false; $trialorgid=0; $orgid=0;$count1=0;$count2=0;
		$data = array();
		$todaydate=date("Y-m-d");
		$user_name = strtolower($request[0]);
		$user_password = $request[1];
		$token = $request[2];
		$qr = $request[3];
		
		if ($qr == 'true') {
            $user_name = Utils::encode5t($user_name);
            $user_password = $user_password;
        }else{
            $user_name = Utils::encode5t($user_name);
            $user_password = Utils::encode5t($user_password);
        }
		
		$sth = $this->db->prepare("SELECT Id, 
                                          EmployeeId,
                                          Password,
                                          UserName,userprofile,
                                          RoleId,
                                          OrganizationId,
                                          CreatedDate,
                                          CreatedById,
                                          LastModifiedDate,
                                          LastModifiedById,
										  AdminSts,trial_OrganizationId,
                                          OwnerId, HRSts 
                                   FROM   UserMaster
                                   WHERE  (UserName = :user_name or username_mobile = :mobile)
                                          AND Password = :user_password and VisibleSts=1 and archive=1 and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0)");
        try{
			$sth->execute(array(':user_name' => $user_name, ':mobile' => $user_name, ':user_password' => $user_password));
        	$count =  $sth->rowCount();
			$row = $sth->fetch();
			//$count =  $sth->rowCount();
			if($count==1){
				$trialorgid = $row->trial_OrganizationId;
				$orgid = $row->OrganizationId;
				
				if($trialorgid!=0){
					$sql="SELECT * FROM TrialOrganization where Id=$trialorgid and '$todaydate' <= end_date AND delete_sts=0";
					$query1 = $this->db->prepare($sql);
					$query1->execute(array());
					$count1 = $query1->rowCount();
					if($count1==0){
						$status="false1";
						$errorMsg="Your trial period has expired!";
						$data['response']=2;
						$data['userperm']=array();
						$data['orgperm']=array();
						
						return $data;
					}
				}
				
				if($orgid!=0){
					$query = "select * from licence_ubihrm where OrganizationId = $orgid and (curdate() between start_date and end_date)";
					$query2 = $this->db->prepare($query);
					$query2->execute(array());
					$count2 = $query2->rowCount();
					if($count2==0)
					{
						$status="false2";
						$errorMsg="Your plan has expired.";
						$data['response']=3;
						$data['userperm']=array();
						$data['orgperm']=array();
						return $data;
					}	
				}
				
				$data['response']=1;
				$data['employeeid']=$row->EmployeeId;
				$data['organization']=$row->OrganizationId;
				$data['userprofileid']=$row->userprofile;
				$organizationname=Utils::getName($row->OrganizationId,'Organization','Name',$this->db);
				$countryid=Utils::getName($row->OrganizationId,'Organization','Country',$this->db);
				$data['countryid']=$countryid;
				
				if (strlen($organizationname) > 16)
					$data['organizationname'] = substr($organizationname, 0, 16) . '..';
				else
					$data['organizationname'] = $organizationname;
				
				$query1 = $this->db->prepare("UPDATE `UserMaster` SET AppId=? WHERE EmployeeId=?");
				$query1->execute(array($token,$row->EmployeeId));
				
				$query5= $this->db->prepare("SELECT ModuleId, ViewPermission, EditPermission, DeletePermission, AddPermission FROM UserProfile_permission WHERE Userprofileid = ? and OrganizationId = ? and ModuleId in (5,42,18,124,180,179,180,19,12,13,14,29,54,170) order by ModuleId");
				$query5->execute(array($row->userprofile, $row->OrganizationId));
				if ($query5->rowCount()>0) {
					$perm5 = array();
					while($permission5 = $query5->fetch()){
						$perm = array();
						$perm['module']=$permission5->ModuleId;
						$perm['view']=$permission5->ViewPermission;
						$perm['edit']=$permission5->EditPermission;
						$perm['delete']=$permission5->DeletePermission;
						$perm['add']=$permission5->AddPermission;
						$perm5[] =$perm;
					}
					$data['userperm']=$perm5;
				}
				
				$query = $this->db->prepare("SELECT ModuleId, ViewPermission FROM OrgPermission WHERE OrgId = ?");
				$query->execute(array($row->OrganizationId));
				if ($query->rowCount()>0) {
					$perm1 = array();
					while($permission = $query->fetch()){
						$perm = array();
						$perm['module']=$permission->ModuleId;
						$perm['view']=$permission->ViewPermission;
						$perm1[] =$perm;
					}
					$data['orgperm']=$perm1;
				}
			}else{
				$status="false";
				$errorMsg="Login failed!";
				$data['response']=0;
				$data['userperm']=array();
				$data['orgperm']=array();
			}
		}
		catch(Exception $e) {
				$errorMsg = 'Message: ' .$e->getMessage();
		}
		return $data;
    }
    
    public function getAllPermission($request)
	{
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		$employeeid = strtolower($request[0]);
		$organization = $request[1];
		$userprofileid   = isset($request[2]) ? $request[2] : 0;
		$data['userperm']=array();
		$query5= $this->db->prepare("SELECT ModuleId, ViewPermission, EditPermission, DeletePermission, AddPermission FROM UserProfile_permission WHERE Userprofileid = ? and OrganizationId = ? and ModuleId in (5, 18, 179, 124, 180, 68, 69, 42, 18, 19, 12, 13, 14, 29, 54, 170, 473) order by ModuleId");
		$query5->execute(array($userprofileid, $organization));
		if ($query5->rowCount()>0) {
			$perm5 = array();
			while($permission5 = $query5->fetch())
			{
				$perm = array();
				$perm['module']=$permission5->ModuleId;
				$perm['view']=$permission5->ViewPermission;
				$perm['edit']=$permission5->EditPermission;
				$perm['delete']=$permission5->DeletePermission;
				$perm['add']=$permission5->AddPermission;
				$perm5[] =$perm;
			}
			$data['userperm']=$perm5;
		}
		
		$data['orgperm']=array();
		$query = $this->db->prepare("SELECT ModuleId, ViewPermission FROM OrgPermission WHERE OrgId = ?");
		$query->execute(array($organization));
		if ($query->rowCount()>0) {
			$perm1 = array();
			while($permission = $query->fetch())
			{
				$perm = array();
				$perm['module']=$permission->ModuleId;
				$perm['view']=$permission->ViewPermission;
				$perm1[] =$perm;
			}
			$data['orgperm']=$perm1;
		}
		return $data;
    }
    
    public function getProfileInfo($request)
	{
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false; $trialorgid=0;
		$data = array();
		$todaydate=date("Y-m-d");
		$employeeid = strtolower($request[0]);
		$organization = $request[1];
		
		$sql="SELECT * FROM TrialOrganization where Id in (select trial_OrganizationId from UserMaster where OrganizationId=$organization and EmployeeId = $employeeid) and '$todaydate' > end_date AND delete_sts=0";
		$sql1 = $this->db->prepare($sql);
		$sql1->execute(array());
		$count1 = $sql1->rowCount();
		if($count1==1){
			$status="false1";
			$errorMsg="Your trial period has expired!";
			$data['Status']='a';
			$data['userperm']=array();
			$data['orgperm']=array();
			return $data;
		}
			
		$query1 = "select * from licence_ubihrm where OrganizationId = $organization and (curdate() between start_date  and  end_date)";
		$query2 = $this->db->prepare($query1);
		$query2->execute(array());
		$count2 = $query2->rowCount();
		if($count2==0)
		{
			$status="false2";
			$errorMsg="Your plan has expired.";
			$data['Status']='b';
			$data['userperm']=array();
			$data['orgperm']=array();
			return $data;
		} 	
			
    	$query = $this->db->prepare("SELECT * FROM EmployeeMaster WHERE Id = ? and OrganizationId = ?");
		$query->execute(array($employeeid, $organization));
		if ($query->rowCount()>0) {
			$perm1 = array();
			if($permission = $query->fetch())
			{
				$perm = array();
				$perm['FirstName']=$permission->FirstName;
				//$perm['LastName']=$permission->LastName;
				$perm['LastName']=isset($permission->LastName)?$permission->LastName:'';

				$perm['FatherName']=$permission->FatherName;
				$perm['DOJ']=isset($permission->DOJ)?date("d-M-Y",strtotime($permission->DOJ)):'';
				$perm['Nationality']=Utils::getName($permission->Nationality,"NationalityMaster","Name",$this->db);
				$perm['BloodGroup']=Utils::getName($permission->BloodGroup,"BloodGroupMaster","Name",$this->db);
				
				$data['Personal'] =$perm;
				
				$company = array();
				if($permission->ImageName!="" ){
					if(!file_exists("public/uploads/".$organization."/".$permission->ImageName))
					{
						$company['ProfilePic']=URL."public/avatars/default.png";
					}
					else{
						$company['ProfilePic']=URL."public/uploads/".$organization."/".$permission->ImageName;
					}
				//$company['ProfilePic']="https://ubitech.ubihrm.com/public/uploads/".$organization."/".$permission->ImageName;
				}else{
					$company['ProfilePic']=URL."public/avatars/default.png";
				}
				//	$company['EmpCode']=$permission->EmployeeCode;
				$company['EmpCode']=isset($permission->EmployeeCode)?$permission->EmployeeCode:'';
				
				$company['Designation']=Utils::getName($permission->Designation,"DesignationMaster","Name",$this->db);
			
							
				$company['Division']=Utils::getName($permission->Division,"DivisionMaster","Name",$this->db);
			
				$company['Location']=Utils::getName($permission->Location,"LocationMaster","Name",$this->db);
				$company['ReportingTo']=Utils::getEmployeeName($permission->ReportingTo,$this->db);
				//$company['ReportingTo']=Utils::getName($permission->ReportingTo,"EmployeeMaster","FirstName",$this->db);
				$reportingtodesignation=Utils::getName($permission->ReportingTo,"EmployeeMaster","Designation",$this->db);
				$reportingtoprofilepic=Utils::getName($permission->ReportingTo,"EmployeeMaster","ImageName",$this->db);
				$company['ReportingToDesignation']=Utils::getName($reportingtodesignation,"DesignationMaster","Name",$this->db);
		
				if($reportingtoprofilepic!=''){
					if(!file_exists("public/uploads/".$organization."/".$reportingtoprofilepic))
					{
							$company['ReportingToProfilePic']=URL."public/avatars/default.png";

					}
					else{
							$company['ReportingToProfilePic']=URL."public/uploads/".$organization."/".$reportingtoprofilepic;
					}
				}else{
						$company['ReportingToProfilePic']=URL."public/avatars/default.png";
				}
				
				$company['Department']=Utils::getName($permission->Department,"DepartmentMaster","Name",$this->db);
				$company['CompanyEmail']=Utils::decode5t($permission->CompanyEmail);
				
				$data['Company'] = $company;
				
				$contact = array();
			
				$contact['Phone']=Utils::decode5t($permission->CurrentContactNumber);
				$contact['Email']=Utils::decode5t($permission->CurrentEmailId);
				$contact['Country']=Utils::getName($permission->CurrentCountry,"CountryMaster","Name",$this->db);
				$contact['Address']=Utils::decode5t($permission->CurrentAddress);
				$contact['City']=Utils::getName($permission->CurrentCity,"CityMaster","Name",$this->db);
				$contact['PostalCode']=$permission->CurrentZipCode;
				
				$data['Contact'] = $contact;
			}
			$status="true";
			$data['Status']='c';
			return $data;
		}	
    }
	
    public function getReportingTeam($request)
	{
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		$employeeid = strtolower($request[0]);
		$organization = $request[1];
    	$ids = $this->getReportingIds($employeeid, $organization);
		$query = $this->db->prepare("SELECT * FROM EmployeeMaster WHERE Id in ($ids) and OrganizationId = $organization order by FirstName");
		$query->execute();
		if ($query->rowCount()>0) {
			$perm1 = array();
			while($permission = $query->fetch()){
				$perm = array();
				$perm['Id']=$permission->Id;
				$perm['Code']=$permission->EmployeeCode;
				$perm['FirstName']=$permission->FirstName;
				$perm['LastName']=$permission->LastName;
				$perm['Designation']=$permission->Designation;
				$perm['DOB']=$permission->DOB;
				$perm['Nationality']=$permission->Nationality;
				$perm['BloodGroup']=$permission->BloodGroup;
				$perm['CompanyEmail']=Utils::decode5t($permission->CompanyEmail);
				
				if($permission->ImageName!=''){
					if(!file_exists("public/uploads/".$organization."/".$permission->ImageName)){
						$perm['ProfilePic']=URL."public/avatars/default.png";
					}else{
						$perm['ProfilePic']=URL."public/uploads/".$organization."/".$permission->ImageName;
					}
				}else{
						$perm['ProfilePic']=URL."public/avatars/default.png";
				}
				$data[]=$perm;
			}	
		}
		return $data;
    }
	
	public function getLeaveChartData($arr)
    {
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; 
		$status=false;
		$data = array();
		$empid   =$arr[0];	//USER ID CONTAINS IN ARRAY FIRST VALUE;
		$orgid = $arr[1];	//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$applydate = date('Y-m-d');	
		$division=0;$department=0; $designation=0; $grade=0; $gender=0; $marital=0; $religion=0;$EmployeeExperience='';
		$emp_type=0;
		$divisionflg=false;$departmentflg=false; $designationflg=false; $gradeflg=false; $genderflg=false; $maritalflg=false; $religionflg=false;$experienceflg=false; $emptypeflg=false;$halfdays=0;
		$fiscaldata=array();
		$doj=date('Y-m-d');
		$mdate=date('Y-m-d');
		$actualstartdate=$startdate=date('Y-04-01');
		$actualenddate=$enddate=date('Y-03-31');
		
		try{
			$preyearfiscalid = $this->getPreviousyearFiscalId($orgid, $this->db);
			$fiscalid = Utils::getFiscalIdForApp($orgid,$applydate, $this->db);
			$sql1 = "SELECT *  FROM FiscalMaster WHERE Id=?";
			$query1 = $this->db->prepare($sql1);
			$query1->execute(array( $fiscalid));
			while($row1=$query1->fetch()){
				$res1=array();
				$res1['id']=$row1->Id;
				$res1['name']=$row1->Name;
				$actualstartdate=$startdate=$row1->StartDate;
				$actualenddate=$enddate=$row1->EndDate;
				$res1['startdate']=Utils::dateformatter($row1->StartDate);
				$res1['enddate']=Utils::dateformatter($row1->EndDate);
				$fiscaldata[]=$res1;
			}
			
			$sql = "SELECT MaritalStatus, Gender, Division, Department, Designation, Grade, Religion, TotalExp,WorkingDays,DOJ,Shift,ProvisionPeriod,EmploymentType,TIMESTAMPDIFF(YEAR, DOJ, CURDATE()) as curyear, TIMESTAMPDIFF(MONTH, DOJ, CURDATE()) as curmonth FROM EmployeeMaster WHERE OrganizationId = ? and Id =? and Is_Delete=0";
			$query = $this->db->prepare($sql);
			$query->execute(array($orgid, $empid));
			while($row = $query->fetch())
			{
				$curyear=0;$curmonth=0;
				$division=$row->Division;
				$department=$row->Department; 
				$designation=$row->Designation; 
				$grade=$row->Grade; 
				$religion=$row->Religion; 
				$gender=$row->Gender; 
				$marital=$row->MaritalStatus;
				$doj=$row->DOJ;
				$ProvisionPeriod=$row->ProvisionPeriod;
				$emp_type=$row->EmploymentType;
				$date1= date("Y-m-d", strtotime("+".$ProvisionPeriod." month ".date($doj)));
				$currdate = date("Y-m-d");
			
				$curyear = ((int)$row->curyear < 0)?0:(int)$row->curyear;
				$curmonth = ((int)$row->curmonth < 0)?0:(int)$row->curmonth;
				$year=($curyear * 12);
				$curmonth=(int)($curmonth - $year);
			}
			
		   $sql = "SELECT * FROM LeaveMaster WHERE OrganizationId = ? and LeaveApply <= CURDATE() and VisibleSts=1 and Compoffsts=0";
			$query = $this->db->prepare($sql);
			try{
				$query->execute(array($orgid));
				$count =  $query->rowCount();
			}catch(Exception $e) {
				$errorMsg = 'Message: ' .$e->getMessage();
			}
			
			if($count>=1)
			{
				$status=true;
				$successMsg=$count." record found";
				
				while($row = $query->fetch())
				{	
					$startdate=$actualstartdate;
					$enddate=$actualenddate;	
					$divisionflg=true;
					$departmentflg=true; 
					$designationflg=true; 
					$gradeflg=true; 
					$religionflg=true; 
					$genderflg=true;
					$emptypeflg=true;
					$maritalflg=true;
					$probationsts=true;
					$experienceflg=true;
					$y=0;$m=0;
					
					$preCFleave=0;
					$leavealotted1=0;
					$totalcreditleave=0;
					$employeeusedleave=0;
					$totalutilizedleave=0;
					$cfrleave=0;
					$balanceleave=0;
					$days_between=365;
					$creditleavepermonth=$row->Monthleave;
					$carriedforward=$row->carriedforward;
					$cappingsts=$row->Caping;
					$leavealotted=$row->LeaveDays;
					$workfromhomests=$row->workfromhomests;
					if($row->Caping==1)
					{
						$leavealotted=round(($row->LeaveDays/12),1);
					}
					$leaveeffectivedate=$row->LeaveApply;
					$startdate1=$startdate;
					
					if($row->EmployeeExperience!=""){
						$exp = explode(',',$row->EmployeeExperience);
						$y=(int)$exp[0];
						$m=(int)$exp[1];
					}
					$n='';
					$n=Utils::getName($row->Id,'LeaveMaster','Name',$this->db);
					$emp_typearr=($row->EmploymentType!=0)?explode(",",$row->EmploymentType):0;
					////////////CHECK IF LEAVE TYPE IS FOR SPECIFIC EMPLOYEES/////////////
					if($row->LeaveUsableSts==1){
						if($row->ProbationSts==0){
							if($currdate  > $date1){
								$probationsts=true;
							}else{$probationsts=false;}
						}
					}
					if($row->LeaveUsableSts==2){
						$empsts=false;
							if($row->EmployeeIds!=""){
								$divisionflg=false;
								$departmentflg=false; 
								$designationflg=false; 
								$gradeflg=false;
								$religionflg=false;
								$genderflg=false; 
								$emptypeflg=false;
								$maritalflg=false;
								$probationsts=false;
								$experienceflg=false;
								
								$temp = explode(",", $row->EmployeeIds);
								for($i=0; $i<count($temp); $i++){
									if($empid==$temp[$i]){ 
										$divisionflg=true;
										$departmentflg=true; 
										$designationflg=true; 
										$gradeflg=true; 
										$religionflg=true;
										$genderflg=true;
										$emptypeflg=true;
										$maritalflg=true;
										$probationsts=true;
										$experienceflg=true;
										$empsts=true;
										if($row->ProbationSts==0){
											if($currdate  > $date1){
												$probationsts=true;
											}else{$probationsts=false;}
										}
										break;
									}
									
								}
							}
							elseif(!$empsts)
							{
								if($row->DivisionId>0){
										if($row->DivisionId==$division){
											$divisionflg=true;
										}else{$divisionflg=false;}
								}
								if($row->DepartmentIds>0){
									if($row->DepartmentIds==$department){
											$departmentflg=true; 
									}else{$departmentflg=false; }
								}
								if($row->DesignationIds>0){
									if($row->DesignationIds==$designation){
											$designationflg=true; 
									}else{$designationflg=false; }
								}
								if($row->GenderId>0){
									if($row->GenderId==$gender){
										$genderflg=true;
									}else{$genderflg=false;}
								}
								if($emp_typearr==0){ 
										$emptypeflg=true;	
								}elseif( in_array($emp_type, $emp_typearr ) ){
										$emptypeflg=true;
								}else{
									$emptypeflg=false;
								}
								if($row->MaritalId>0){
									if($row->MaritalId==$marital){
										$maritalflg=true;
									}else{$maritalflg=false;}
								}
								if($row->GradeId>0){
									if($row->GradeId==$grade){
										$gradeflg=true; 
									}else{$gradeflg=false; }
								}
								if($row->ReligionId>0){
									if($row->ReligionId==$religion){
										$religionflg=true; 
									}else{$religionflg=false; }
								}
								if(($row->EmployeeExperience!='0,0') || ($row->EmployeeExperience!='')){
									if(($y!='') || ($m!='')){//echo $curyear." ".$y;
										if($curyear >= $y){//echo $curmonth." ".$m;
											if($curmonth >=  $m){
											$experienceflg=true; 
											}elseif(($curmonth <  $m) && ($curyear > $y)){
												$experienceflg=true; 
											}else{$experienceflg=false; }
										}elseif(($curmonth >=  $m) && ($curyear >= $y)){
											$experienceflg=true; 
										}else{$experienceflg=false; }
									}
								}
									
								///////////////////IF PROBATION STATUS IS 1 THEN LEAVE IS APPLICATION FOR ALL/////////////////////
								
								if($row->ProbationSts==0){	
									if(strtotime($currdate)  > strtotime($date1)){
										$probationsts=true;
									}else{$probationsts=false;}
								}
							}
					}
					
					if($divisionflg && $departmentflg && $designationflg && $gradeflg && $genderflg && $maritalflg && $religionflg && $probationsts && $experienceflg && $emptypeflg)
					{
						if((strtotime($leaveeffectivedate) >= strtotime($startdate)) &&(strtotime($leaveeffectivedate) < strtotime($enddate)) ){
							if((strtotime($date1) < strtotime($leaveeffectivedate)) ){
								if($row->Caping==1){
									$start=date('Y' ,strtotime($leaveeffectivedate));
									$end = date('Y' , strtotime($enddate));
									$Y1=date('m',strtotime($leaveeffectivedate));
									$M1=date('m',strtotime($enddate));
									$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
									$leavealotted1=($diff1+1) * $leavealotted;
									
									$countmonth=date('Y' ,strtotime($leaveeffectivedate));
									$currentdate = date('Y' , strtotime(date("Y-m-d")));
									$Y=date('m',strtotime($leaveeffectivedate));
									$M=date('m',strtotime(date("Y-m-d")));
									$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
									$totalcreditleave=($diff+1) * $creditleavepermonth;
								}else{
									$start = strtotime($leaveeffectivedate);
									$end = strtotime($enddate);
									$days_between = (abs($end - $start) / 86400);
									$leavealotted1=round(($days_between * $leavealotted)/365);
									$totalcreditleave=$leavealotted1;
								}
							}else{
								if($row->ProbationSts==1){
									if((strtotime($doj) <= strtotime($leaveeffectivedate)) ){
										if($row->Caping==1){
											$start=date('Y' ,strtotime($leaveeffectivedate));
											$end = date('Y' , strtotime($enddate));
											$Y1=date('m',strtotime($leaveeffectivedate));
											$M1=date('m',strtotime($enddate));
											$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
											$leavealotted1=($diff1+1) * $leavealotted;
											
											$countmonth=date('Y' ,strtotime($leaveeffectivedate));
											$currentdate = date('Y' , strtotime(date("Y-m-d")));
											$Y=date('m',strtotime($leaveeffectivedate));
											$M=date('m',strtotime(date("Y-m-d")));
											$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
											$totalcreditleave=($diff+1) * $creditleavepermonth;
										}else{
											$start = strtotime($leaveeffectivedate);
											$end = strtotime($enddate);
											$days_between = (abs($end - $start) / 86400);
											$leavealotted1=round(($days_between * $leavealotted)/365);
											$totalcreditleave=$leavealotted1;
										}
									}else{
										if($row->Caping==1){
											$start=date('Y' ,strtotime($doj));
											$end = date('Y' , strtotime($enddate));
											$Y1=date('m',strtotime($doj));
											$M1=date('m',strtotime($enddate));
											$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
											$leavealotted1=($diff1+1) * $leavealotted;
											
											$countmonth=date('Y' ,strtotime($doj));
											$currentdate = date('Y' , strtotime(date("Y-m-d")));
											$Y=date('m',strtotime($doj));
											$M=date('m',strtotime(date("Y-m-d")));
											$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
											$totalcreditleave=($diff+1) * $creditleavepermonth;
										}else{
											$start = strtotime($doj);
											$end = strtotime($enddate);
											$days_between = (abs($end - $start) / 86400);
											$leavealotted1=round(($days_between * $leavealotted)/365);
											
											$totalcreditleave=$leavealotted1;
										}
									}
								}else{
									if($row->Caping==1){
										$start=date('Y' ,strtotime($date1));
										$end = date('Y' , strtotime($enddate));
										$Y1=date('m',strtotime($date1));
										$M1=date('m',strtotime($enddate));
										$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
										$leavealotted1=($diff1+1) * $leavealotted;
										
										$countmonth=date('Y' ,strtotime($date1));
										$currentdate = date('Y' , strtotime(date("Y-m-d")));
										$Y=date('m',strtotime($date1));
										$M=date('m',strtotime(date("Y-m-d")));
										$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
										$totalcreditleave=($diff+1) * $creditleavepermonth;
									}else{
										$start = strtotime($date1);
										$end = strtotime($enddate);
										$days_between = (abs($end - $start) / 86400);
										$leavealotted1=round(($days_between * $leavealotted)/365);
										
										$totalcreditleave=$leavealotted1;
									}
								}
							}
						}
						
						if(strtotime($leaveeffectivedate) < strtotime($startdate)){
							if((strtotime($date1) < strtotime($startdate1)) ){
								if($row->Caping==1){
									$start=date('Y' ,strtotime($startdate1));
									$end = date('Y' , strtotime($enddate));
									$Y1=date('m',strtotime($startdate1));
									$M1=date('m',strtotime($enddate));
									$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
									$leavealotted1=($diff1+1) * $leavealotted;
									
									$countmonth=date('Y' ,strtotime($startdate1));
									$currentdate = date('Y' , strtotime(date("Y-m-d")));
									$Y=date('m',strtotime($startdate1));
									$M=date('m',strtotime(date("Y-m-d")));
									$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
									$totalcreditleave=($diff+1) * $creditleavepermonth;
								}else{
									$start = strtotime($startdate1);
									$end = strtotime($enddate);
									$days_between = (abs($end - $start) / 86400);
									$leavealotted1=round(($days_between * $leavealotted)/365);
									
									$totalcreditleave=$leavealotted1;
								}
							}
							else{
								if($row->ProbationSts==1){
									if(strtotime($doj) < strtotime($startdate) ){
										if($row->Caping==1){
											$start=date('Y' ,strtotime($startdate1));
											$end = date('Y' , strtotime($enddate));
											$Y1=date('m',strtotime($startdate1));
											$M1=date('m',strtotime($enddate));
											$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
											$leavealotted1=($diff1+1) * $leavealotted;
											
											$countmonth=date('Y' ,strtotime($startdate1));
											$currentdate = date('Y' , strtotime(date("Y-m-d")));
											$Y=date('m',strtotime($startdate1));
											$M=date('m',strtotime(date("Y-m-d")));
											$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
											$totalcreditleave=($diff+1) * $creditleavepermonth;
										}else{
											$start = strtotime($startdate1);
											$end = strtotime($enddate);
											$days_between = (abs($end - $start) / 86400);
											$leavealotted1=round(($days_between * $leavealotted)/365);
											$totalcreditleave=$leavealotted1;
										}
									}
									if(strtotime($doj) >= strtotime($startdate)){
										if($row->Caping==1){
											$start=date('Y' ,strtotime($doj));
											$end = date('Y' , strtotime($enddate));
											$Y1=date('m',strtotime($doj));
											$M1=date('m',strtotime($enddate));
											$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
											$leavealotted1=($diff1+1) * $leavealotted;
											
											$countmonth=date('Y' ,strtotime($doj));
											$currentdate = date('Y' , strtotime(date("Y-m-d")));
											$Y=date('m',strtotime($doj));
											$M=date('m',strtotime(date("Y-m-d")));
											$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
											$totalcreditleave=($diff+1) * $creditleavepermonth;
										}else{
											$start = strtotime($doj);
											$end = strtotime($enddate);
											$days_between = (abs($end - $start) / 86400);
											$leavealotted1=round(($days_between * $leavealotted)/365);
											
											$totalcreditleave=$leavealotted1;
										}
									}
								}else{
									if($row->Caping==1){
										$start=date('Y' ,strtotime($date1));
										$end = date('Y' , strtotime($enddate));
										$Y1=date('m',strtotime($date1));
										$M1=date('m',strtotime($enddate));
										$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
										$leavealotted1=($diff1+1) * $leavealotted;
										
										$countmonth=date('Y' ,strtotime($date1));
										$currentdate = date('Y' , strtotime(date("Y-m-d")));
										$Y=date('m',strtotime($date1));
										$M=date('m',strtotime(date("Y-m-d")));
										$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
										$totalcreditleave=($diff+1) * $creditleavepermonth;
									}else{	$start = strtotime($doj);
										$end = strtotime($enddate);
										$days_between = (abs($end - $start) / 86400);
										$leavealotted1=round(($days_between * $leavealotted)/365);
										
										$totalcreditleave=$leavealotted1;
									}
								}
							}
						}
						
						if($row->carriedforward==1){
							$sql1 = "SELECT * FROM EmployeeCarriedForward WHERE OrganizationId = ? and EmployeeId = ?  and FiscalId = ? and LeaveTypeId=?";
							$query1 = $this->db->prepare($sql1);
							$query1->execute(array($orgid,$empid,$preyearfiscalid,$row->Id));
							$count1 =  $query1->rowCount();
							$row1 = $query1->fetch();
							
							if($count1>=1){
								$preCFleave=$row1->CFLeave;	
							}
						}
						
						$employeeusedleave=0;
						$sql2 = "Select * from EmployeeLeaveChild as empchild,EmployeeLeave as empleave where empchild.EmployeeLeaveId=empleave.Id and empleave.OrganizationId = ? and empleave.EmployeeId =? and empleave.LeaveTypeId=? and empleave.FiscalId in(1,$fiscalid) and empchild.LossOfPay=0 and empleave.LeaveStatus=2 and empchild.Entitled=1";
						$query2 = $this->db->prepare($sql2);
						$query2->execute(array($orgid,$empid,$row->Id));
						$count2 =  $query2->rowCount();
						while($row2 = $query2->fetch()){
							if($row2->HalfDaySts == 1){
								$employeeusedleave = $employeeusedleave + 0.5;
							}else{
								$employeeusedleave++;
							}
						}
					
						$cfrleave=0;
						$sql3 = "Select * from EmployeeLeaveChild as empchild,EmployeeLeave as empleave where empchild.EmployeeLeaveId=empleave.Id and empchild.LossOfPay=0 and empchild.LeaveStatus=2  and empleave.OrganizationId = ? and empleave.EmployeeId =?  and empleave.LeaveTypeId=? and empleave.LeaveStatus=2 and empchild.CarriedForward=1 and empleave.FiscalId=?";
						$query3 = $this->db->prepare($sql3);
						$query3->execute(array($orgid,$empid,$row->Id,$fiscalid));
						while($row3 = $query3->fetch()){
							if($row3->HalfDaySts == 1){
								$cfrleave = $cfrleave + 0.5;
							}else{
								$cfrleave++;
							}
						}
						
						$totalutilizedleave=$employeeusedleave + $cfrleave;
						$leftalloted=$totalcreditleave-$totalutilizedleave;
						
						if($leftalloted>0)
							$balanceleave=(($preCFleave-$cfrleave) + $leftalloted);
						
						$res = array();
						$res1 = array();
						$res['id'] = (int)$row->Id;
						$res['name']="";
						$words = explode(" ", $row->Name);
						foreach ($words as $w) {
							$res['name'] .= ucwords($w[0]);
						}
						$res['days'] = (int)$leavealotted1;//////////Alloted leave days for a year///////////////
						$res['usedleave'] = $totalutilizedleave;
						$res['allocatedleftleaves']=$leftalloted;
						$res['leftleave'] = ($preCFleave - $cfrleave) + ($totalcreditleave - $employeeusedleave);/////THIS YEAR BALANCE/////
						$res['totalleave'] = $preCFleave + $leavealotted1 ; ////////////TOTAL LEAVE BALANCE FOR THIS YEAR//////////////
						$res1[]=$res;
						$data['leavesummary']['data'][] = $res;
					}
				}	
			}else{
				$status=true;
				$successMsg = LEAVETYPE_MODULE_GETALL;
			}
		}catch(Exception $e) {
			$status=false;
			$errorMsg = 'Message: ' .$e->getMessage();
		}
		return $data;
    }
	
	public function getCarryforwardleave($empid,$fiscalid,$orgid,$leavetype)
	{
		$name =0;
		$sql = "SELECT CFLeave FROM EmployeeCarriedForward WHERE EmployeeId = ? and OrganizationId =? and LeaveTypeId=? and FiscalId=?";
        $query = $this->db->prepare($sql);
		try{
			$query->execute(array( $empid ,$orgid, $leavetype, $fiscalid));
			while($row = $query->fetch())
			{
				$name = $row->CFLeave;
			}
		}catch(Exception $e) {
			
		}
		return $name;
	}
	
	public function getHolidays($request)
	{
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		$userid = strtolower($request[0]);
		$orgid = $request[1];
    	$ids = Utils::getReportingIds($userid, $this->db, $orgid);
		$userdesig=Utils::getName($userid, "EmployeeMaster", "Division", $this->db); 

		$sql = "SELECT * FROM HolidayMaster WHERE OrganizationId = ? AND DateFrom>=CURDATE() and find_in_set($userdesig,DivisionId)  order by DateFrom asc  limit 7";
        $query = $this->db->prepare($sql);
		$query->execute(array(  $orgid ));
		$count =  $query->rowCount();
		if($count>=1){
			while($row = $query->fetch()){
				$temp="";
				$res = array();					
				$res['name'] = "Holiday - ".$row->Name; 
				$res['message'] = $row->Description; 
				
				if($row->DateFrom != $row->DateTo){	
					$dates=date("jS M", strtotime($row->DateFrom));
					$datet=date("jS M", strtotime($row->DateTo));
					$res['date']= $dates." to ".$datet;
				}else{
					$dates=date("jS M", strtotime($row->DateTo));
					$res['date']= $dates;
				}
				$data[] = $res;
			}
		}
		return $data;
    }
	
	public function getAttSummaryChart($arr)
	{
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array(); 
		$mid   =$arr[0];	//USER ID CONTAINS IN ARRAY FIRST VALUE;
		$orgid = $arr[1];//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$startdate = date("Y-m-d");
		$enddate =  date("Y-m-d");
		$startdate1=date('Y-m-1');
		$enddate1=date("Y-m-t");
		$present =0;$absent =0;$weekoff =0;$halfday =0;$holiday =0;$leave =0;$compoff =0;$workfromhome =0;$unpaidleave =0;$unpaidhalfday =0;$total =0;$month=0;
		
		$sql1 = "SELECT  * FROM AttendanceMaster where OrganizationId= ? and AttendanceStatus IN (1,2,3,4,5,6,7,8,9,10) and (AttendanceDate between ? and ?) and EmployeeId =? ";
		$query1 = $this->db->prepare($sql1);
		$query1->execute(array($orgid,$startdate1,$enddate1,$mid));
		$total = $query1->rowCount();
		if($total > 0){
			while($row1 = $query1->fetch())
			{
				$res = array();
				$month=	date("M", strtotime($row1->AttendanceDate));
				if($row1->AttendanceStatus==1){
						 $present++;
				}elseif($row1->AttendanceStatus==2){
						$absent++;
				}/* elseif($row1->AttendanceStatus==3){
						$weekoff++;
				}elseif($row1->AttendanceStatus==4){
						$halfday++;
				}elseif($row1->AttendanceStatus==5){
						$holiday++;
				} */elseif($row1->AttendanceStatus==6){
						$leave++;
				}/* elseif($row1->AttendanceStatus==7){
						$compoff++;
				}elseif($row1->AttendanceStatus==8){
						$workfromhome++;
				}elseif($row1->AttendanceStatus==9){
						$unpaidleave++;
				}elseif($row1->AttendanceStatus==10){
						$unpaidhalfday++; 
				} */    
			   $res['month'] = $month;
			   $res['present'] = $present;
			   $res['absent'] = $absent; 
			   /* $res['weekoff'] = $weekoff;
			   $res['halfday'] = $halfday;
			   $res['holiday'] = $holiday; */ 
			   $res['leave'] = $leave;
			   /* $res['compoff'] = $compoff;
			   $res['workfromhome'] = $workfromhome;
			   $res['unpaidleave'] = $unpaidleave; 
			   $res['unpaidhalfday'] = $unpaidhalfday; */
			   $data['att'] = $res;
			}
		}
		return $data ;
	}
	
	public function getapproval($arr)
	{
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array(); 
		$userid  = $arr[0];	//USER ID CONTAINS IN ARRAY FIRST VALUE;
        $orgid=$arr[1];	//ORG ID CONTAINS IN ARRAY SECOND VALUE;	
		$datafor = $arr[2];
		$stsn=$this->getstsid($arr[2],'LeaveStatus');
		$startdate = date("Y-m-1");
		$enddate = date("Y-m-t");
		$sql2= "SELECT * FROM  `FiscalMaster` WHERE  `OrganizationId` =$orgid AND  `FiscalSts` =1";
		$query2      = $this->db->prepare($sql2);
		$query2->execute();
		$count = $query2->rowCount();
		if($count==1){
			if($row2 = $query2->fetch()){
				$startdate = $row2->StartDate;
				$enddate = $row2->EndDate;
			}
		}
		
		$sts=1;
		$hrsts=0;
		$sWhere = "";
		if($hrsts==1){
			$sWhere = " WHERE LeaveStatus=$stsn and OrganizationId= $orgid and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0) and (DATE(ApplyDate) between '$startdate' and '$enddate')";
		}else{ 
			$sWhere = "WHERE LeaveStatus=$stsn and OrganizationId= $orgid AND Id IN (SELECT LeaveId FROM LeaveApproval Where ApproverId=$userid) and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0) and (DATE(ApplyDate) between '$startdate' and '$enddate')";
		 } 
		
		$present =0;$absent =0;$leave =0;$total =0;$month=0;
		$sql1 = "SELECT * FROM EmployeeLeave $sWhere ORDER BY ApplyDate desc";
		$query1 = $this->db->prepare($sql1);
		$query1->execute();
		$total = $query1->rowCount();
		
		if($total > 0){
			while($row1 = $query1->fetch()){
				$sts=$this->getApproverSts($row1->Id,$userid);
				$res = array();
				$res['total'] = $total;
				$res['Id'] = $row1->Id;
				$res['name'] = $this->getName($row1->EmployeeId);
				$lsts = $row1->LeaveStatus;
				if($lsts==3){
					$res['LeaveStatus']='Pending';
				}
				if($lsts==2){
					$res['LeaveStatus']='Approved';
				}
				if($lsts==1){
					$res['LeaveStatus']='Rejected';
				}
				$res['FDate'] = date("jS M", strtotime($row1->LeaveFrom));
				$res['TDate'] = date("jS M", strtotime($row1->LeaveTo));
				$res['ApplyDate'] = date("d-M-Y", strtotime($row1->ApplyDate));
				$res['Ldays'] = $row1->LeaveValidDays;
				$res['FromDayType'] = $row1->FromDayType;
				$res['ToDayType'] = $row1->ToDayType;
				$res['TimeOfTo'] = $row1->TimeOfTo;
				$res['LeaveTypeId'] = $row1->LeaveTypeId;
				$res['LeaveType'] = $this->getName1($row1->LeaveTypeId,"LeaveMaster",'Name',$this->db);
				$res['LeaveReason'] = $row1->LeaveReason;
				$res['Pstatus']  = $this->getpendingatstatus($lsts, $row1->Id);
				$Pstatus=$res['Pstatus'];
				if($Pstatus!=$userid && $Pstatus!=0  ){
					$name=$this->getName($Pstatus);
					$res['Pstatus']="Pending at $name";
				}
				else{
					$res['Pstatus']="";
				}
				
				$sq = "SELECT HRSts  FROM UserMaster WHERE EmployeeId = ? and OrganizationId = ? ";
				$query = $this->db->prepare($sq);
				try{
					$query->execute(array($userid, $orgid ));
					while($row = $query->fetch())
					{
						$res['HRSts'] = $row->HRSts;
					}
				}catch(Exception $e) {
					
				}
				$data[] = $res;
			}
		}
		return $data ;
	}
	
	////////////////////////////////////////////SALARY/PAYROLL EXPENSE APPROVAL'S COMMON FUNCTION///////////////////////////////////////////////
	//////////////////////////////////////////////////////////WRITTEN ON 30TH JAN 2020/////////////////////////////////////////////////////
	public function getApproverSts1($id,$userid)
	{
		$flg =false;
		$employee=0;
		$sql = "SELECT * FROM ClaimApproval WHERE ClaimId = ? ";
        $query = $this->db->prepare($sql);
		try{
			$query->execute(array( $id ));
			while($row = $query->fetch()){
				if($row->ApproverSts==3){
					$employee=$row->ApproverId;
					break;
				}
			}
			if($employee ==  $userid){
				$flg = true;
			}
		}catch(Exception $e) {
			
		}
		return $flg;
	}
	
	public function getpendingatstatus1($sts,$expenseid)
	{
		if($sts==3){
			$pendingapprover=$this->getApproverPendingSts1($expenseid,3);
			return	$pendingapprover;
		}else{
			return $this->getleavetype($sts);
		}
	}
	
	public function getApproverPendingSts1($id,$sts)
	{
		$name ="0";
		if($sts==2)//approved
			$sql = "SELECT * FROM ClaimApproval where ClaimId=? and ApproverSts=? order by Id desc limit 1";
		else//pending	
			$sql = "SELECT * FROM ClaimApproval where ClaimId=? and ApproverSts=? order by Id asc limit 1";
		$query = $this->db->prepare($sql);
		try{
			$query->execute(array( $id,$sts ));
			while($row = $query->fetch())
			{
				$name = $row->ApproverId;
			}
		}catch(Exception $e) {}

		return $name;
	}
	////////////////////////////////SALARY/PAYROLL EXPENSE APPROVAL'S COMMON FUNCTION ENDS HERE//////////////////////////////////////
	
	//////////////////////////////////////////////SALARY EXPENSE APPROVAL TAB ID 4 AND MODULT ID 170/////////////////////////////////////////////////
	//////////////////////////////////////////////////////////WRITTEN ON 30TH JAN 2020/////////////////////////////////////////////////////
	public function getExpenseApproval($arr)
	{
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array(); 
		$userid  = $arr[0];	 //USER ID CONTAINS IN ARRAY FIRST VALUE;
        $orgid=$arr[1];	//ORG ID CONTAINS IN ARRAY SECOND VALUE;	
		$datafor = $arr[2];
		$stsn=$this->getstsid($arr[2],'LeaveStatus');
		$startdate = date("Y-m-1");
		$enddate = date("Y-m-t");
		
		$sql2= "SELECT * FROM  `FiscalMaster` WHERE  `OrganizationId` =$orgid AND  `FiscalSts` =1";
		$query2= $this->db->prepare($sql2);
		$query2->execute();
		$count = $query2->rowCount();
		if($count==1){
			if($row2 = $query2->fetch()){
				$startdate = $row2->StartDate;
				$enddate = $row2->EndDate;
			}
		}
		
		$sts=1;
		$hrsts=0;
		$sWhere = "";
		if($hrsts==1){
			$sWhere = " WHERE ApproverSts=$stsn and OrganizationId= $orgid and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0) and (DATE(FromDate) between '$startdate' and '$enddate')";
		}else{ 
			$sWhere = "WHERE ApproverSts=$stsn and OrganizationId= $orgid AND Id IN (SELECT ClaimId FROM ClaimApproval Where ApproverId=$userid) and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0) and (DATE(FromDate) between '$startdate' and '$enddate')";
		} 
		
		$present =0;$absent =0;$leave =0;$total =0;$month=0;
		$sql1 = "SELECT * FROM ClaimsMaster $sWhere ORDER BY FromDate desc";
		$query1 = $this->db->prepare($sql1);
		$query1->execute();
		$total = $query1->rowCount();
		if($total > 0){
			while($row1 = $query1->fetch()){
				$sts=$this->getApproverSts1($row1->Id,$userid);
				$res = array();
				$res['total'] = $total;
				$res['Id'] = $row1->Id;
				$res['Name'] = $this->getName($row1->EmployeeId);
				$lsts = $row1->ApproverSts;
				if($lsts==3)
				{
					$res['ApproverSts']='Pending';
				}
				if($lsts==2){
					$res['ApproverSts']='Approved';
				}
				if($lsts==1){
					$res['ApproverSts']='Rejected';
				}
				$res['FromDate'] = date("d-M-Y", strtotime($row1->FromDate));
				$res['ClaimHeadId'] =$row1->ClaimHead;
				$res['ClaimHead'] = Utils::getName($row1->ClaimHead,'ClaimsHead','Name',$this->db);
				$res['Purpose'] = $row1->Purpose;
				$res['TotalAmt'] = $row1->TotalAmt;
				$res['Doc'] = $row1->Doc;
				$divisionId=Utils::getName($row1->EmployeeId,'EmployeeMaster','Division',$this->db);
				$divisionName=ucwords(Utils::getName($divisionId, "DivisionMaster","Name", $this->db));
				$res['EmpCurrency'] =  Utils::getDivisioncurrency($divisionId,$this->db);
				$res['Pstatus']  = $this->getpendingatstatus1($lsts, $row1->Id);
				$Pstatus=$res['Pstatus'];
				if($Pstatus!=$userid && $Pstatus!=0  ){
					$name=$this->getName($Pstatus);
					$res['Pstatus']="Pending at $name";
				}
				else{
					$res['Pstatus']="";
				}
				$data[] = $res;
			}
		}
		return $data ;
	}
	
	public function ApprovedExpense($arr)
	{
	    $eid=$arr[0];
		$orgid=$arr[1];
		$expenseid=$arr[2];
		$comment=$arr[3];
		$sts=$arr[4];
		$date =date('Y-m-d H:i:s');
	    $result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false; $approver_val="";
	    $data = array();
		
		if($sts==2){$approver_val='approved';}
		else{$approver_val='rejected';}	
		
		$sql = "UPDATE ClaimApproval SET ApproverSts=? ,ApproverComment=?, ApprovalDate=? where ClaimId=? and ApproverId=? and OrganizationId=? and ApproverSts =3";
		$query = $this->db->prepare($sql);
		$query->execute(array($sts,$comment,$date,$expenseid,$eid,$orgid));
		$count = $query->rowCount();
		if ($count >= 1) {
			$empid=Utils::getName($expenseid,'ClaimsMaster','EmployeeId',$this->db);
			$empname=ucwords(strtolower(Utils::getEmployeeName($empid,$this->db)));
			$approvername=ucwords(strtolower(Utils::getEmployeeName($eid,$this->db)));
			$applydate=Utils::getName($expenseid,'ClaimsMaster','FromDate',$this->db);
			$claimheadid=Utils::getName($expenseid,'ClaimsMaster','ClaimHead',$this->db);
			$claimhead=Utils::getName($claimheadid,'ClaimsHead','Name',$this->db);
			$amt=Utils::getName($expenseid,'ClaimsMaster','TotalAmt',$this->db);
			$msg="<b>$empname</b> salary expense request <b>$approver_val</b> by <b>$approvername</b> | Applied On: <b>$applydate</b> | Expense Head: <b>$claimhead</b> | Total Amt.: <b>$amt</b>";
			$sql = "INSERT INTO ActivityHistoryMaster ( LastModifiedById, Module, ActionPerformed,  OrganizationId) VALUES (?, ?, ?, ?)";
			$query = $this->db->prepare($sql);
			$query->execute(array($eid, "UBIHRM APP", $msg, $orgid));
			
			$status =true;
			if($sts==2){
				$successMsg = " Expense request is approved succesfully";
				$sql1 = "select * from ClaimApproval WHERE ClaimId = ? and ApproverSts<>2 and OrganizationId=?";
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array( $expenseid, $orgid));
				if($query1->rowCount()==0){
					$sql2 = "UPDATE ClaimsMaster SET ApproverSts=?,LastModifiedDate=?,LastModifiedById=?,ApproverId=? WHERE Id =? ";
					$query2 = $this->db->prepare($sql2);
					$query2->execute(array(2,$date,$eid,$eid, $expenseid));
				}else{
					if($r=$query1->fetch()){
						$approverid=$r->ApproverId;
						$approveremail=Utils::decode5t(Utils::getName($approverid ,'EmployeeMaster','CompanyEmail',$this->db));
						$approvername=Utils::getName($approverid,'EmployeeMaster','FirstName',$this->db);
	
						$sql3 = "select * from ClaimsMaster WHERE Id = ?";
						$query3 = $this->db->prepare($sql3);
						$query3->execute(array( $expenseid));
						while($r1=$query3->fetch()){
							//$ApproverId=$r1->ApproverId;
							$seniorname=Utils::getName($r1->ApproverId,'EmployeeMaster','FirstName',$this->db);
							$EmployeeId=$r1->EmployeeId;
							$designationId = Utils::getName($r1->EmployeeId,'EmployeeMaster','Designation',$this->db);
							$designationName = ucwords(Utils::getName($designationId,'DesignationMaster','Name',$this->db));
							$departmentId = Utils::getName($r1->EmployeeId,'EmployeeMaster','Department',$this->db);
							$departmentName = ucwords(Utils::getName($departmentId,' DepartmentMaster','Name',$this->db));
							$divisionId=Utils::getName($r1->EmployeeId,'EmployeeMaster','Division',$this->db);
							$divisionName=ucwords(Utils::getName($divisionId, "DivisionMaster","Name", $this->db));
							$orgname=ucwords(Utils::getName($orgid, "Organization","Name", $this->db));
							$empcurency =  Utils::getDivisioncurrency($divisionId,$this->db);
							$FromDate=$r1->FromDate;
							$TotalAmt=$r1->TotalAmt;
							$head = Utils::getName($r1->ClaimHead,'ClaimsHead','Name',$this->db);
							$purpose=$r1->Purpose;
							$empname = Utils::getEmployeeName($r1->EmployeeId, $this->db);
						}
						
						$approvelink=URL."approvalbymail/expencepproval/$approverid/$orgid/$decodeid/2";
						$rejectlink=URL."approvalbymail/expencepproval/$approverid/$orgid/$decodeid/1";
						
						$sub="Expense Request";
						$msg="<table>
									<tr><td>Hello $approvername,</td></tr>
									<tr><td>$empname has requested for expense.</td></tr>
									<tr><td>Designation: $designationName </td></tr>
									<tr><td>Department: $departmentName </td></tr>
									<tr><td>Expense head: $head</td></tr>
									<tr><td>Request Amount: $TotalAmt $empcurency</td></tr>
									<tr><td>Purpose: $purpose</td></tr>
									<tr><td>Request date: $FromDate</td></tr><br>
									<tr><td>Thanks</td></tr>
									<tr><td>$orgname</td></tr>
								</table>
								<table>
								<tr><td><br/><br/>
									<a href='$approvelink'   style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: green;
									-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px green; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Approve</a>
									&nbsp;&nbsp;
									&nbsp;&nbsp;
									<a href='$rejectlink'  style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: brown;
									-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px brown; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Reject</a>
									<br/><br/>
									</td>															
									</tr>	
								</table>";
							Utils::sendMail($approveremail,"UBIHRM",$sub,$msg);
							Utils::Trace($approveremail." ".$title." ".$msg);
					}
				}
			}else{
				$sql2 = "UPDATE ClaimsMaster SET ApproverSts =?, LastModifiedDate=?, LastModifiedById=?,ApproverId=? WHERE Id =? ";
				$query2 = $this->db->prepare($sql2);
				$query2->execute(array(1, $date, $eid, $eid, $expenseid));
				$successMsg = " Expense request reject succesfully";
			}
		}else{
			$status =false;
			$errorMsg="Something went wrong.";
		}
		$result["data"] =$data;
		$result['status']=$status;
		$result['successMsg']=$successMsg;
		$result['errorMsg']=$errorMsg;
		return $result;
	}
	//////////////////////////////////////SALARY EXPENSE APPROVAL TAB ID 4 AND MODULT ID 170 ENDS HERE///////////////////////////////////////
	
	//////////////////////////////////////////////PAYROLL EXPENSE APPROVAL TAB ID 8 AND MODULT ID 473/////////////////////////////////////////////////
	//////////////////////////////////////////////////////////WRITTEN ON 04TH FEB 2020/////////////////////////////////////////////////////
	public function getPayrollExpenseApproval($arr)
	{
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array(); 
		$userid  = $arr[0];		//USER ID CONTAINS IN ARRAY FIRST VALUE;
        $orgid=$arr[1];		//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$datafor = $arr[2];
		$stsn=$this->getstsid($arr[2],'LeaveStatus');
		$startdate = date("Y-m-1");
		$enddate = date("Y-m-t");
		
		$sql2= "SELECT * FROM  `FiscalMaster` WHERE  `OrganizationId` =$orgid AND  `FiscalSts` =1";
		$query2      = $this->db->prepare($sql2);
		$query2->execute();
		$count = $query2->rowCount();
		if($count==1){
			if($row2 = $query2->fetch()){
				$startdate = $row2->StartDate;
				$enddate = $row2->EndDate;
			}
		}
		
		$sts=1;
		$hrsts=0;
		$sWhere = "";
		 if($hrsts==1){
			$sWhere = " WHERE ApproverSts=$stsn and OrganizationId= $orgid and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0) and (DATE(FromDate) between '$startdate' and '$enddate')";
		}else{ 
			$sWhere = "WHERE ApproverSts=$stsn and OrganizationId= $orgid AND Id IN (SELECT ClaimId FROM ClaimApproval Where ApproverId=$userid) and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0) and (DATE(FromDate) between '$startdate' and '$enddate')";
		 } 
		
		$present =0;$absent =0;$leave =0;$total =0;$month=0;
		$sql1 = "SELECT * FROM ClaimsMaster $sWhere ORDER BY FromDate desc";
		$query1 = $this->db->prepare($sql1);
		$query1->execute();
		$total = $query1->rowCount();
		if($total > 0){
			while($row1 = $query1->fetch())
			{
				$sts=$this->getApproverSts1($row1->Id,$userid);
				$res = array();
				$res['total'] = $total;
				$res['Id'] = $row1->Id;
				$res['Name'] = $this->getName($row1->EmployeeId);
				$lsts = $row1->ApproverSts;
				if($lsts==3)
				{
					$res['ApproverSts']='Pending';
				}
				if($lsts==2){
					$res['ApproverSts']='Approved';
				}
				if($lsts==1){
					$res['ApproverSts']='Rejected';
				}
				$res['FromDate'] = date("d-M-Y", strtotime($row1->FromDate));
				$res['ClaimHeadId'] =$row1->ClaimHead;
				$res['ClaimHead'] = Utils::getName($row1->ClaimHead,'ClaimsHead','Name',$this->db);
				$res['Purpose'] = $row1->Purpose;
				$res['TotalAmt'] = $row1->TotalAmt;
				$res['Doc'] = $row1->Doc;
				$divisionId=Utils::getName($row1->EmployeeId,'EmployeeMaster','Division',$this->db);
				$divisionName=ucwords(Utils::getName($divisionId, "DivisionMaster","Name", $this->db));
				$res['EmpCurrency'] =  Utils::getDivisioncurrency($divisionId,$this->db);
				$res['Pstatus']  = $this->getpendingatstatus1($lsts, $row1->Id);
				$Pstatus=$res['Pstatus'];
				if($Pstatus!=$userid && $Pstatus!=0  ){
					$name=$this->getName($Pstatus);
					$res['Pstatus']="Pending at $name";
				}
				else{
					$res['Pstatus']="";
				}
				$data[] = $res;
			}
		}
		return $data ;
	}
	
	public function ApprovedPayrollExpense($arr)
	{
	    $eid=$arr[0];
		$orgid=$arr[1];
		$expenseid=$arr[2];
		$comment=$arr[3];
		$sts=$arr[4];
		$date =date('Y-m-d H:i:s');
	    $result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false; $approver_val="";
	    $data = array();
		
		if($sts==2){$approver_val='approved';}
		else{$approver_val='rejected';}	
		
		$sql = "UPDATE ClaimApproval SET ApproverSts=? ,ApproverComment=?, ApprovalDate=? where ClaimId=? and ApproverId=? and OrganizationId=? and ApproverSts =3";
		$query = $this->db->prepare($sql);
		$query->execute(array($sts,$comment,$date,$expenseid,$eid,$orgid));
		$count = $query->rowCount();
		
		if ($count >= 1) {
			$empid=Utils::getName($expenseid,'ClaimsMaster','EmployeeId',$this->db);
			$empname=ucwords(strtolower(Utils::getEmployeeName($empid,$this->db)));
			$approvername=ucwords(strtolower(Utils::getEmployeeName($eid,$this->db)));
			$applydate=Utils::getName($expenseid,'ClaimsMaster','FromDate',$this->db);
			$claimheadid=Utils::getName($expenseid,'ClaimsMaster','ClaimHead',$this->db);
			$claimhead=Utils::getName($claimheadid,'ClaimsHead','Name',$this->db);
			$amt=Utils::getName($expenseid,'ClaimsMaster','TotalAmt',$this->db);
			$msg="<b>$empname</b> payroll expense request <b>$approver_val</b> by <b>$approvername</b> | Applied On: <b>$applydate</b> | Expense Head: <b>$claimhead</b> | Total Amt.: <b>$amt</b>";
			$sql = "INSERT INTO ActivityHistoryMaster ( LastModifiedById, Module, ActionPerformed,  OrganizationId) VALUES (?, ?, ?, ?)";
			$query = $this->db->prepare($sql);
			$query->execute(array($eid, "UBIHRM APP", $msg, $orgid));
			
			$status =true;
			if($sts==2){
				$successMsg = " Expense request is approved succesfully";
				$sql1 = "select * from ClaimApproval WHERE ClaimId = ? and ApproverSts<>2 and OrganizationId=?";
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array( $expenseid, $orgid));
				if($query1->rowCount()==0){
					$sql2 = "UPDATE ClaimsMaster SET ApproverSts=?,LastModifiedDate=?,LastModifiedById=?,ApproverId=? WHERE Id =? ";
					$query2 = $this->db->prepare($sql2);
					$query2->execute(array(2,$date,$eid,$eid, $expenseid));
				}else{
					if($r=$query1->fetch()){
						$approverid=$r->ApproverId;
						$approveremail=Utils::decode5t(Utils::getName($approverid ,'EmployeeMaster','CompanyEmail',$this->db));
						$approvername=Utils::getName($approverid,'EmployeeMaster','FirstName',$this->db);
	
						$sql3 = "select * from ClaimsMaster WHERE Id = ?";
						$query3 = $this->db->prepare($sql3);
						$query3->execute(array( $expenseid));
						while($r1=$query3->fetch()){
							$seniorname=Utils::getName($r1->ApproverId,'EmployeeMaster','FirstName',$this->db);
							$EmployeeId=$r1->EmployeeId;
							$designationId = Utils::getName($r1->EmployeeId,'EmployeeMaster','Designation',$this->db);
							$designationName = ucwords(Utils::getName($designationId,'DesignationMaster','Name',$this->db));
							$departmentId = Utils::getName($r1->EmployeeId,'EmployeeMaster','Department',$this->db);
							$departmentName = ucwords(Utils::getName($departmentId,' DepartmentMaster','Name',$this->db));
							$divisionId=Utils::getName($r1->EmployeeId,'EmployeeMaster','Division',$this->db);
							$divisionName=ucwords(Utils::getName($divisionId, "DivisionMaster","Name", $this->db));
							$orgname=ucwords(Utils::getName($orgid, "Organization","Name", $this->db));
							$empcurency =  Utils::getDivisioncurrency($divisionId,$this->db);
							$FromDate=$r1->FromDate;
							$TotalAmt=$r1->TotalAmt;
							$head = Utils::getName($r1->ClaimHead,'ClaimsHead','Name',$this->db);
							$purpose=$r1->Purpose;
							$empname = Utils::getEmployeeName($r1->EmployeeId, $this->db);
						}
						
						$approvelink=URL."approvalbymail/expencepproval/$approverid/$orgid/$decodeid/2";
						$rejectlink=URL."approvalbymail/expencepproval/$approverid/$orgid/$decodeid/1";
						
						$sub="Expense Request";
						$msg="<table>
									<tr><td>Hello $approvername,</td></tr>
									<tr><td>$empname has requested for expense.</td></tr>
									<tr><td>Designation: $designationName </td></tr>
									<tr><td>Department: $departmentName </td></tr>
									<tr><td>Expense head: $head</td></tr>
									<tr><td>Request Amount: $TotalAmt $empcurency</td></tr>
									<tr><td>Purpose: $purpose</td></tr>
									<tr><td>Request date: $FromDate</td></tr><br>
									<tr><td>Thanks</td></tr>
									<tr><td>$orgname</td></tr>
								</table>
								<table>
								<tr><td><br/><br/>
									<a href='$approvelink'   style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: green;
									-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px green; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Approve</a>
									&nbsp;&nbsp;
									&nbsp;&nbsp;
									<a href='$rejectlink'  style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: brown;
									-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px brown; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Reject</a>
									<br/><br/>
									</td>															
									</tr>	
								</table>";
							Utils::sendMail($approveremail,"UBIHRM",$sub,$msg);
							Utils::Trace($approveremail." ".$title." ".$msg);
					}
				}
			}else{
				$sql2 = "UPDATE ClaimsMaster SET ApproverSts =?, LastModifiedDate=?, LastModifiedById=?,ApproverId=? WHERE Id =? ";
				$query2 = $this->db->prepare($sql2);
				$query2->execute(array(1, $date, $eid, $eid, $expenseid));
				$successMsg = " Expense request reject succesfully";
			}
		}else{
			$status =false;
			$errorMsg="Something went wrong.";
		}
		$result["data"] =$data;
		$result['status']=$status;
		$result['successMsg']=$successMsg;
		$result['errorMsg']=$errorMsg;
		return $result;
	}
	////////////////////////////////////PAYROLL EXPENSE APPROVAL TAB ID 8 AND MODULT ID 473 ENDS HERE//////////////////////////////////////
	
	public function getteamapproval()
    {
		$res= array();$count=0;
        $org_id     = isset($_REQUEST['orgid']) ? $_REQUEST['orgid'] : '0';
        $empid     = isset($_REQUEST['empid']) ? $_REQUEST['empid'] : '0';//Login Person 
		$data = array(); 
		$startdate = date("Y-m-1");
		$enddate = date("Y-m-t");
		
		$sql2= "SELECT * FROM  `FiscalMaster` WHERE  `OrganizationId` =$org_id AND  `FiscalSts` =1";
		$query2      = $this->db->prepare($sql2);
		$query2->execute();
		$count = $query2->rowCount();
		if($count==1){
			if($row2 = $query2->fetch()){
				$startdate = $row2->StartDate;
				$enddate = $row2->EndDate;
			}
		}
	
		$reportingids=$this->getReportingIds($empid, $org_id);
		$swhere="";
		$sql="SELECT * FROM UserMaster WHERE EmployeeId= $empid";
		$query1  = $this->db->prepare($sql);
		$query1->execute();
		$row = $query1->fetch();
		$admin_sts=$row->AdminSts;
		$hr_sts=$row->HRSts;
		
		if($admin_sts==1){
			$swhere="and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0) and EmployeeId<>$empid and (DATE(ApplyDate) between '$startdate' and '$enddate')";
		}else{
			$swhere="and EmployeeId in(".$reportingids.") and (DATE(ApplyDate) between '$startdate' and '$enddate')";
		}
		
		$sql1 ="SELECT * FROM `EmployeeLeave` WHERE  OrganizationId =".$org_id."   $swhere order by Id desc  ";
		$query      = $this->db->prepare($sql1);
		$query->execute();
		while($row1 = $query->fetch())
		{
			$res = array();
			$res['Id'] = $row1->Id;
			$res['name'] = $this->getName($row1->EmployeeId);
			$lsts = $row1->LeaveStatus;
			if($lsts==3)
			{
				$res['LeaveStatus']='Pending';
			}
			if($lsts==2){
				$res['LeaveStatus']='Approved';
			}
			if($lsts==1){
				$res['LeaveStatus']='Rejected';
			}
			if($lsts==5){
				$res['LeaveStatus']='Withdrawn';
			}
			$res['FDate'] = date("d M", strtotime($row1->LeaveFrom));
			$res['TDate'] = date("d M", strtotime($row1->LeaveTo));
			$res['ApplyDate'] = date("d-M-Y", strtotime($row1->ApplyDate));
			$res['Ldays'] = $row1->LeaveValidDays;
			$res['FromDayType'] = $row1->FromDayType;
			$res['ToDayType'] = $row1->ToDayType;
			$res['TimeOfTo'] = $row1->TimeOfTo;
			$res['LeaveTypeId'] = $row1->LeaveTypeId;
			$res['LeaveType'] = $this->getName1($row1->LeaveTypeId, 'LeaveMaster');
			$res['LeaveReason'] = $row1->LeaveReason;
			$res['Pstatus']  = $this->getpendingatstatus($lsts, $row1->Id);
			$Pstatus=$res['Pstatus'];
			if($Pstatus!=$empid && $Pstatus!=0  ){
				$name=$this->getName($Pstatus);
				$res['Pstatus']="Pending at $name";
				$res['sts']=false;
			}elseif($Pstatus==$empid){
				$res['Pstatus']="";
				$res['sts']=true;
			}else{
				$res['Pstatus']="";
				$res['sts']=false;
			}
			$res['HRSts'] = $hr_sts;
			
			$data[] = $res;
		}
		return $data ;
    }
	
	public function getTeamTimeoffapproval($arr){
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array(); 
		$userid  = $arr[0];//USER ID CONTAINS IN ARRAY FIRST VALUE;
        $orgid=$arr[1];//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$startdate = date("Y-m-1");	
		$enddate = date("Y-m-t");
		
		$sql2= "SELECT * FROM  `FiscalMaster` WHERE  `OrganizationId` =$orgid AND  `FiscalSts` =1";
		$query2      = $this->db->prepare($sql2);
		$query2->execute();
		$count = $query2->rowCount();
		if($count==1){
			if($row2 = $query2->fetch()){
				$startdate = $row2->StartDate;
				$enddate = $row2->EndDate;
			}
		}
		
		$reportingids=$this->getReportingIds($userid, $orgid);
		$swhere="";
		$sql="SELECT * FROM UserMaster WHERE EmployeeId= $userid";
		$query1  = $this->db->prepare($sql);
		$query1->execute();
		$row = $query1->fetch();
		$admin_sts=$row->AdminSts;
		$hr_sts=$row->HRSts;
		
		if($admin_sts==1){
			$swhere="and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0) and EmployeeId<>$userid and (TimeofDate between '$startdate' and '$enddate')";
		}else{
			$swhere="and EmployeeId in(".$reportingids.") and (TimeofDate between '$startdate' and '$enddate')";
		}
		
		$sql1 = "SELECT * FROM  Timeoff  WHERE  OrganizationId =".$orgid."	$swhere 
		ORDER BY  TimeofDate desc";
		$query1 = $this->db->prepare($sql1);
		$query1->execute();
		$total = $query1->rowCount();
		if($total > 0){
			while($row1 = $query1->fetch()){
				$res = array();
				$res['total'] = $total;
				$res['Id'] = $row1->Id;
				$res['name'] = $this->getName($row1->EmployeeId);
				$lsts = $row1->ApprovalSts;
				if($lsts==3){
					$res['LeaveStatus']='Pending';
				}
				if($lsts==2){
					$res['LeaveStatus']='Approved';
				}
				if($lsts==1){
					$res['LeaveStatus']='Rejected';
				}
				if($lsts==5){
					$res['LeaveStatus']='Withdrawn';
				}
				$res['FDate'] = date("g:i a", strtotime($row1->TimeFrom));
				$TDate = date("g:i a", strtotime($row1->TimeTo));
				if($res['FDate']==$TDate){
				    $res['TDate']="";
				}else{
				   $res['TDate'] =" to ".$TDate; 
				}
				$res['ApplyDate'] = date("d-M-Y", strtotime($row1->CreatedDate));
				$res['TimeofDate'] = date("d-M-Y", strtotime($row1->TimeofDate));
				$res['LeaveReason'] = $row1->Reason;
				$res['Pstatus']  = $this->gettimeoffpendingatstatus($lsts, $row1->Id);
				$Pstatus=$res['Pstatus'];
				if($Pstatus!=$userid && $Pstatus!=0 ){
					$name=$this->getName($Pstatus);
					$res['Pstatus']="Pending at $name";
					$res['sts']=false;
				}
				elseif($Pstatus==$userid){
					$res['Pstatus']="";
					$res['sts']=true;
				}
				else{
					$res['Pstatus']="";
					$res['sts']=false;
				}
				$res['HRSts'] = $hr_sts;
	
				$data[] = $res;
			}
		}
		return $data ;
	}
	
	public function getReportingIds($empid, $orgid)
	{
		$ids = 0;
		$parentid=$empid;
		if($parentid!="0" && $parentid!=""){
			while($parentid!=""){
				$sql1 = "SELECT Id FROM EmployeeMaster WHERE OrganizationId = ? and ReportingTo in ( $parentid ) and  DOL='0000-00-00' and Is_Delete=0 ";
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array($orgid));
				$parentid="";
				while($row1 = $query1->fetch()){
					if($parentid==""){
						$parentid = $row1->Id;
					}else{
						$parentid .= ", ".$row1->Id;
					}
					if($ids==""){
						$ids = $row1->Id;
					}else{
						$ids .= ",".$row1->Id;
					}
				}
			}
		}
		return $ids;
	}
		
	public function getapprovalCount($arr)
	{
		$result = array();
		$leavecount=0; $timeoffcount=0; $expenseoffcount=0; $total=0;$errorMsg=""; $successMsg=""; $status=false;$count=0;
		$data = array(); 
		$userid  = $arr[0];	//USER ID CONTAINS IN ARRAY FIRST VALUE;
        $orgid=$arr[1];
		$startdate = date("Y-m-1");
		$enddate = date("Y-m-t");
		$sql2= "SELECT * FROM  `FiscalMaster` WHERE  `OrganizationId` =$orgid AND  `FiscalSts` =1";
		$query2 = $this->db->prepare($sql2);
		$query2->execute();
		$count = $query2->rowCount();
		if($count==1){
			if($row2 = $query2->fetch()){
				$startdate = $row2->StartDate;
				$enddate = $row2->EndDate;
			}
		}
		
		$sql = "SELECT * FROM EmployeeLeave WHERE OrganizationId= $orgid AND LeaveStatus = 3  AND Id in (SELECT LeaveId from (select * from LeaveApproval where  ApproverSts = 3 Group by LeaveId) s where ApproverId=$userid) and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0) and (DATE(ApplyDate) between '$startdate' and '$enddate')" ;
        $query = $this->db->prepare($sql);
		$query->execute();
		$leavecount =  $query->rowCount();
		
		$sql1="SELECT * FROM Timeoff WHERE OrganizationId= $orgid and ApprovalSts=3 and Id IN (SELECT TimeofId from (select * from TimeoffApproval where  ApproverSts = 3 Group by TimeofId) s where ApproverId=$userid) and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0) and (TimeofDate between '$startdate' and '$enddate') ";
        $query1 = $this->db->prepare($sql1);
		$query1->execute();
		$timeoffcount =  $query1->rowCount();
		
		$sql2="SELECT * FROM ClaimsMaster WHERE OrganizationId= $orgid and ApproverSts=3 and Id IN (SELECT ClaimId from (select * from ClaimApproval where ApproverSts = 3 Group by ClaimId) s where ApproverId=$userid) and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0) and (FromDate between '$startdate' and '$enddate') ";
        $query1 = $this->db->prepare($sql2);
		$query1->execute();
		$expenseoffcount =  $query1->rowCount();
		
		$data['total'] = $leavecount + $timeoffcount + $expenseoffcount;
		return $data ;
	}
	
	public function getStatus($id,$type)
	{
		$name ="";
		if($id == 0){
			$name="waiting for approval";
		}else{
			$sql = "SELECT DisplayName FROM OtherMaster WHERE ActualValue = ? and OtherType='$type'";
			$query = $this->db->prepare($sql);
			try{
				$query->execute(array( $id ));
				while($row = $query->fetch())
				{
					$name = $row->DisplayName;
				}
			}catch(Exception $e) {

			}
		}
		return $name;
	}
	
	public function getApproverPendingSts($id,$sts)
	{
			$name ="0";
			if($sts==2)//approved
				$sql = "SELECT * FROM LeaveApproval where LeaveId=? and ApproverSts=? order by Id desc limit 1";
			else//pending	
				$sql = "SELECT * FROM LeaveApproval where LeaveId=? and ApproverSts=? order by Id asc limit 1";
			$query = $this->db->prepare($sql);
			try{
				$query->execute(array( $id,$sts ));
				while($row = $query->fetch())
				{
					$name = $row->ApproverId;
				}
			}catch(Exception $e) {}

			return $name;
	}
	
	
	public function getApproverSts($id,$userid)
	{
		$flg =false;
		$employee=0;
		$sql = "SELECT * FROM LeaveApproval WHERE LeaveId = ? ";
        $query = $this->db->prepare($sql);
		try{
			$query->execute(array( $id ));
			while($row = $query->fetch())
			{
				if($row->ApproverSts==3){
					$employee=$row->ApproverId;
					break;
				}
			}
			if($employee ==  $userid){
					$flg = true;
				}
		}catch(Exception $e) {
			
		}
		return $flg;
	}
	
	public function getstsid($id,$type)
	{
		$name ="";
		$sql = "SELECT ActualValue  FROM OtherMaster WHERE DisplayName = ? and OtherType = ? ";
		
		  $query = $this->db->prepare($sql);
		try{
			$query->execute(array( $id, $type ));
			while($row = $query->fetch())
			{
				$name = $row->ActualValue;
			}
		}catch(Exception $e) {
			
		}
		return $name;
	}
	
	public function getName($id)
	{
		$name ="";
		$sql = "SELECT FirstName,LastName  FROM EmployeeMaster WHERE Id = ? ";
		
		  $query = $this->db->prepare($sql);
		try{
			$query->execute(array($id));
			while($row = $query->fetch())
			{
				$name = ucwords(strtolower($row->FirstName." ".$row->LastName));
			}
		}catch(Exception $e) {
			
		}
		return $name;
	}
	
	public function Approvedleave($arr)
	{
	    $eid=$arr[0];
		$orgid=$arr[1];
		$leaveid=$arr[2];
		$comment=$arr[3];
		$sts=$arr[4];
		$date =date('Y-m-d H:i:s');
        //$time = date('H:i:s');
	    $result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
	    $data = array();
		
		if($sts==2){$approver_val='approved';}
		else{$approver_val='rejected';}	
		
		$sql = "UPDATE LeaveApproval SET ApproverSts=? ,ApproverComment=?, ApprovalDate=? where LeaveId=? and ApproverId=? and OrganizationId=?";
		$query = $this->db->prepare($sql);
		$query->execute(array($sts,$comment,$date,$leaveid,$eid,$orgid));
		$count = $query->rowCount();
		
		if($count>0){
			$sql = "SELECT EmployeeId, LeaveFrom, LeaveTo, ApplyDate, LeaveTypeId FROM EmployeeLeave WHERE Id=?";
			$query = $this->db->prepare($sql);
			$query->execute(array($leaveid));
			$row = $query->fetch();
			$approvername=$this->getName($eid);
			$empname=$this->getName($row->EmployeeId);
			$applyon=$row->ApplyDate;
			$from=$row->LeaveFrom;
			$to=$row->LeaveTo;
			$leavetype=$this->getName1($row->LeaveTypeId,"LeaveMaster",'Name',$this->db);
			$msg="<b>$empname</b> Leave <b>$approver_val</b> by <b>$approvername</b> | Applied On: <b>$applyon</b> | From: <b>$from</b> | To: <b>$to</b> | Leave Type: <b>$leavetype</b>";
			$sql = "INSERT INTO ActivityHistoryMaster ( LastModifiedById, Module, ActionPerformed,  OrganizationId) VALUES (?, ?, ?, ?)";
			$query = $this->db->prepare($sql);
			$query->execute(array($eid, "UBIHRM APP", $msg, $orgid));
					
			if($sts==1){
				$sql1 = "UPDATE EmployeeLeave SET LeaveStatus=? ,ApproverComment=? WHERE Id=? and OrganizationId=? ";
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array($sts,$comment,$leaveid,$orgid ));
			}
			$status =true;
			$successMsg="Leave approved Succesfully";
		}else{
			$status =false;
			$errorMsg="Something went wrong.";
		}
		$result["data"] =$data;
		$result['status']=$status;
		$result['successMsg']=$successMsg;
		$result['errorMsg']=$errorMsg;
		return $result;
	}
	
	public function getpendingatstatus($sts,$leaveid)
	{
		if($sts==3){
						$pendingapprover=$this->getApproverPendingSts($leaveid,3);
					//$pendingapp=$this->getName($pendingapprover);	
//$pendingapp=Utils::getName($pendingapprover,"EmployeeMaster","FirstName",$this->db);	
	
				
						/*if($pendingapp=="")
							return "Pending";
						else
							return "Pending at $getApproverPendingSts";*/
				return	$pendingapprover;
				}
				else
				{
					return $this->getleavetype($sts);
					
				}
	}
	
	public function getpendingatstatusname($sts,$leaveid)
	{
		if($sts==3){
						$pendingapprover=$this->getApproverPendingSts($leaveid,3);
					$pendingapp=$this->getName($pendingapprover);	
//$pendingapp=Utils::getName($pendingapprover,"EmployeeMaster","FirstName",$this->db);	
	
				
						if($pendingapp=="")
							return "Pending";
						else
							return "Pending at $pendingapp";
				return	$pendingapp;
				}else{
						return $this->getleavetype($sts);
					}
	}
	
	public function getleavetype($val){
		$status = "info"; $label="Pending";                 
		if($val==1){ $status = "danger"; $label="Rejected";  }
		elseif($val==2){ $status = "success"; $label="Approved";  }
		elseif($val==4){ $status = "warning"; $label="Cancel";  }	
		elseif($val==5){ $status = "info"; $label="Withdrawn";  }			
		elseif($val==6){ $status = "success"; $label="Issued";  }			
		elseif($val==7){ $status = "warning"; $label="Pending at admin";  }			
		return $label;
    }
	
	public function gettimeoffpendingatstatusname($sts,$timeoffid,$approverid)
	{
		if($sts==3){
					//	$pendingapprover=$this->getApproverPendingSts($timeoffid,3);
					$pendingapp=$this->getName($approverid);	
//$pendingapp=Utils::getName($pendingapprover,"EmployeeMaster","FirstName",$this->db);	
	
				
						if($pendingapp=="")
							return "Pending";
						else
							return "Pending at $pendingapp";
				return	$pendingapp;
				}else{
						return $this->getleavetype($sts);
					}
	}
	
	/////////////////   service to fetch leave summary  //////////
	
	public function getLeaveList()
    {
        $uid   = isset($_REQUEST['uid']) ? $_REQUEST['uid'] : 0;
        $query = $this->db->prepare("SELECT Id, `ApplyDate`, `LeaveFrom`, `LeaveTo`, LeaveValidDays, `LeaveReason`, `LeaveStatus`, `ApproverComment`, (select Name from LeaveMaster where Id=LeaveTypeId) as leavetype, (select compoffsts from LeaveMaster where Id=LeaveTypeId) as compoffsts FROM `EmployeeLeave` WHERE EmployeeId=? order by Id desc limit 30");
		
        $res= array();
		$query->execute(array($uid ));
			if ($query->rowCount()>0) {
				// foreach ($query->result() as $row) {
				while($row = $query->fetch()){
					$data   = array();
					$data['leaveid'] = $row->Id;
					$todaydate=date("Y-m-d");
					
					if(strtotime($todaydate)>strtotime($row->LeaveFrom))
						$data['withdrawlsts'] = false;
					else
						$data['withdrawlsts'] = true;
					//$data['date'] = date("dS M Y", strtotime($row->ApplyDate));
					$data['date'] = date("d-M-Y", strtotime($row->ApplyDate));
					$data['leavetype'] = $row->leavetype;
					$data['from'] = date("d M", strtotime($row->LeaveFrom));
					$data['to']   = date("d M", strtotime($row->LeaveTo));
					$data['compoffsts']   = $row->compoffsts;
					$data['days']  = ' (' . $row->LeaveValidDays . ')';
					$data['status']  = $this->getpendingatstatusname($row->LeaveStatus, $row->Id);// TODO dynamic with the status pending on which employee.
					$data['reason']  = $row->LeaveReason != '' || $row->LeaveReason != null ? $row->LeaveReason : '-';
					$data['comment'] = $row->ApproverComment != '' || $row->ApproverComment != null ? $row->ApproverComment : '-';
					//$data['comment']=$row->ApproverComment;
					$res[]           = $data;
				}
			}
        echo json_encode($res);
    }
	
	
	/////////////////   service to fetch Timeoff summary  //////////
	
	public function getTimeoffList()
    {
        $uid   = isset($_REQUEST['uid']) ? $_REQUEST['uid'] : 0;
        $query = $this->db->prepare("SELECT Id,`TimeofDate`, TIME_FORMAT(TimeFrom, '%H:%i') as TimeFrom ,TIME_FORMAT(TimeTo, '%H:%i') as TimeTo,  `Reason`, `ApprovalSts`, `ApproverId`,`ApproverComment` FROM `Timeoff` WHERE EmployeeId=? order by Id desc limit 30");
		
        $res= array();
		$query->execute(array($uid ));
			if ($query->rowCount()>0) {
       // foreach ($query->result() as $row) {
		  while($row = $query->fetch())
			{
            $data   = array();
			$data['timeoffid'] = $row->Id;
			$todaydate=date("Y-m-d");
			$data['withdrawlsts'] = true;
			if(strtotime($todaydate)>strtotime($row->TimeofDate))
				$data['withdrawlsts'] = false;
            $data['date'] = date("dS M'Y", strtotime($row->TimeofDate));
            $data['from'] = ($row->TimeFrom);
            $data['to']   = ($row->TimeTo);
            $data['status']  = $this->gettimeoffpendingatstatusname($row->ApprovalSts, $row->Id,$row->ApproverId);// TODO dynamic with the status pending on which employee.
            $data['reason']  = $row->Reason != '' || $row->Reason != null ? $row->Reason : '-';
            $data['comment'] = $row->ApproverComment != '' || $row->ApproverComment != null ? $row->ApproverComment : '-';
            //$data['comment']=$row->ApproverComment;
            $res[]           = $data;
        }
			}
        echo json_encode($res);
    }
	
	public function explode_time($time)
	{ //explode time and convert into seconds
	
		if($time!=""){
		if($time < '00:00')
		{
			$time = explode(':', $time);
			$time = $time[0] * 60 - $time[1];
			//Utils::Trace($time);
		}
		else{
			//Utils::Trace($time);
			$time = explode(':', $time);
			$time = $time[0] * 60 + $time[1];
		}
		}
			return $time;
	}
	
	public function Approve($request)
	{
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false; $leavereason=""; $duration=""; $leavedays="";
		$data = array();
        $mid   = $request[0];	//USER ID CONTAINS IN ARRAY FIRST VALUE;
		$orgid = $request[1];	//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$leaveid = $request[2];
		
		$empid=0;
		$mdate = date("Y-m-d H:i:s");
		//$fiscalid=Utils::getFiscalId($mdate,$this->db);
		$sql = "UPDATE LeaveApproval SET ApproverSts =?, ApprovalDate =?, ApproverComment=? WHERE LeaveId =? AND ApproverId=? and OrganizationId=? and  ApproverSts =3 and  ApprovalDate ='0000-00-00 00:00:00'";
		try{
			$query = $this->db->prepare($sql);
			$query->execute(array($request[3], $mdate, $request[4], $leaveid, $mid, $orgid));
			$count =  $query->rowCount();	
         	
			if ($count >= 1) {
					
				$empid=Utils::getName($leaveid,'EmployeeLeave','EmployeeId',$this->db);
				$empname=ucwords(Utils::getName($empid,'EmployeeMaster','FirstName',$this->db));
				$msg="A leave has been approved of $empname";
				$sql = "INSERT INTO ActivityHistoryMaster ( LastModifiedById, Module, ActionPerformed,  OrganizationId) VALUES (?, ?, ?, ?)";
				$query = $this->db->prepare($sql);
				$query->execute(array($mid, "Employee Leave", $msg, $orgid));
				$status =true;
				$emp_name="";
				$emp_mail="";
				$sql3="select CompanyEmail, FirstName, LastName from EmployeeMaster where Id=(select EmployeeId from EmployeeLeave where Id=$leaveid)";
				
				$query3=$this->db->prepare($sql3);
				$query3->execute();
				if($row3=$query3->fetch()){
						
					$emp_mail=Utils::decode5t($row3->CompanyEmail);
					$emp_name=$row3->ucwords(FirstName)." ".$row3->ucwords(LastName);
					//Utils::Trace("name and email".$emp_mail." ".$emp_name);
				}
			   ///////////////if application is approve///////////////////////////
			   if($request[3]==2){
				
				$successMsg = EMPLOYEELEAVE_LEAVE_APPROVE;
				$sql1 = "select * from LeaveApproval WHERE LeaveId = ? and ApproverSts=3 and OrganizationId=?";
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array( $leaveid, $orgid));
				$con=$query1->rowCount();
				if($r=$query1->fetch())
				{
					/////////////////////////get next approval and send mail to them////////////////
					 
					$approverid=$r->ApproverId;
					$approveremail=Utils::decode5t(Utils::getName($approverid ,'EmployeeMaster','CompanyEmail',$this->db));
					$leavereason="";
					$leavefrom="";
					$leaveto="";
					$fromhalfsts="";
					$tohalfsts="";
					$fromtypehalf="";
					$totypehalf="";
					$fromhalf="";
					$tohalf="";
					$leavetypeid="";
					$leavedays=0;
					$sql2 = "select * from EmployeeLeave WHERE Id = ?";
					$query2 = $this->db->prepare($sql2);
					$query2->execute(array( $leaveid));
					while($r1=$query2->fetch())
					{
						
						$leavereason=$r1->LeaveReason;
						$leavefrom=date("d/m/Y", strtotime($r1->LeaveFrom));
						$leaveto=date("d/m/Y", strtotime($r1->LeaveTo));
						$fromhalfsts=$r1->FromDayType;
						$tohalfsts=$r1->ToDayType;
						$fromtypehalf=$r1->TimeOfFrom;
						$totypehalf=$r1->TimeOfTo;
						$leavetypeid=$r1->LeaveTypeId;
						$leavedays=$r1->LeaveValidDays;
						$resumptiondate = Utils::dateformatter($r1->ResumptionDate);
						$substitute = Utils::getEmployeeName($r1->SubstituteEmployeeId, $this->db);
					}
					
					if($fromhalfsts==2){
						if($fromtypehalf==1){
						$fromhalf=" (1st half)";
						}else{
							$fromhalf=" (2nd half)";
						}
					}
					if($tohalfsts==2){
						if($totypehalf==1){
							$tohalf=" (1st half)";
						}else{
							$tohalf=" (2nd half)";
						}
					}
					
					$duration="";
					if($leavefrom==$leaveto){
					$duration="$leavefrom $fromhalf";	
					}else{
					$duration=" from $leavefrom $fromhalf to $leaveto $tohalf";	
					}
					
					if($con==1){
						/////////////// FOR LAST APPROVAL,WE DON'T NEED TO SEND APPROVAL LINK, BECAUSE LAST APPROVAL WILL DISTRIBUTE THE LEAVE WHICH CAN NOT BE DONE THROUGH MAIL /////////////////
						
						    $approverhistory="";
							$sql = "SELECT * FROM LeaveApproval WHERE OrganizationId = ? AND LeaveId = ? AND ApproverSts<>3 ";
							$query = $this->db->prepare($sql);
							$query->execute(array($orgid, $leaveid));
							$count =  $query->rowCount();
							if($count>=1){
								$approverhistory="<p><b>Approval History</b></p>
								<table border='1' style=' border-collapse: collapse;width:70%'>
								<tr style=' background-color: rgba(107, 58, 137, 0.91);color: rgba(255, 247, 247, 1);'>
													
													<th>Approval Status</th>
													<th>Approver</th>
													<th>Approval Date</th>
													<th>Remarks</th>
												</tr>
								";
							}
							while($r=$query->fetch()){
								 
								$approvername=ucwords(Utils::getEmployeeName($r->ApproverId,$this->db));
								$approvalsts=$r->ApproverSts;
								if($approvalsts==1){
									$approvalsts="Rejected";
								}elseif($approvalsts==2){
									$approvalsts="Approved";
								}elseif($approvalsts==3){
									$approvalsts="Pending";
								}elseif($approvalsts==4){
									$approvalsts="Cancel";
								}elseif($approvalsts==5){
									$approvalsts="Withdrawn";
								}elseif($approvalsts==7){
									$approvalsts="Escalated";
								}
								$approvaldate="";
								$approvaldate=Utils::datetimeformatter($r->ApprovalDate);
								$approvercomment=$r->ApproverComment;
								$approverhistory.="<tr>
													
									<th>$approvalsts</th>
									<th>$approvername</th>
									<th>$approvaldate</th>
									<th>$approvercomment</th>
									</tr>";
							}
							if($count>=1){
								$approverhistory.="</table>";
							}
							
							///////////////////  Fetching Leave History //////////////////
							
							$leavehistory="";
							$leavehistoryarr=$this->getEmployeeAllLeaveTypeForMail($orgid,$empid,$leavetypeid);
							foreach($leavehistoryarr as $key => $value) {
								
								$leavetyp=$value['name'];
								$ent=$value['days'];
								$usedleave=$value['usedleave'];
								$leftleave=$value['leftleave'];
								$carryforward=$value['carryforward'];
								$leavehistory.="
								<p><b>Leave History</b></p>
										
										<table border='1' style=' border-collapse: collapse;width:70%'>
												<tr style=' background-color: rgba(107, 58, 137, 0.91);color: rgba(255, 247, 247, 1);'>
													<th>Leave Type</th>
													<th>Carried Forward</th>
													<th>Leave Entitled</th>
													<th>Leave Utilized</th>
													<th>Balance Leave</th>
												</tr>
												<tr>
													<td><center>$leavetyp</center></td>
													<td><center>$carryforward</center></td>
													<td><center>$ent</center></td>
													<td><center>$usedleave</center></td>
													<td><center>$leftleave</center></td>
													
												</tr>
										</table><br>
								";
							}
						$title="Leave approval";
						$msg="<table>
								<tr><td>Requested by: $emp_name</td></tr>
								<tr><td>Reason for leave: $leavereason</td></tr>
								<tr><td>Duration: $duration</td></tr>
								<tr><td>Leave days: $leavedays</td></tr>
								<tr><td>Resumption Date: $resumptiondate </td></tr>
								<tr><td>Substitute: $substitute</td></tr>
								</table>
								$leavehistory
									</br>
								$approverhistory<br>
								<table>
								<tr><td>Thanks</td></tr></table>
						";
					}else{
						
						$approverhistory="";
						$sql = "SELECT * FROM LeaveApproval WHERE OrganizationId = ? AND LeaveId = ? AND ApproverSts<>3 ";
						$query = $this->db->prepare($sql);
						$query->execute(array($orgid, $leaveid));
						$count =  $query->rowCount();
						if($count>=1){
							$approverhistory="<p><b>Approval History</b></p>
							<table border='1' style=' border-collapse: collapse;width:70%'>
							<tr style=' background-color: rgba(107, 58, 137, 0.91);color: rgba(255, 247, 247, 1);'>	
								<th>Approval Status</th>
								<th>Approver</th>
								<th>Approval Date</th>
								<th>Remarks</th>
							</tr>";
						}
						while($r=$query->fetch()){
							$approvername=ucwords(Utils::getEmployeeName($r->ApproverId,$this->db));
							$approvalsts=$r->ApproverSts;
							if($approvalsts==1){
								$approvalsts="Rejected";
							}elseif($approvalsts==2){
								$approvalsts="Approved";
							}elseif($approvalsts==3){
								$approvalsts="Pending";
							}elseif($approvalsts==4){
								$approvalsts="Cancel";
							}elseif($approvalsts==5){
								$approvalsts="Withdrawn";
							}elseif($approvalsts==7){
								$approvalsts="Escalated";
							}
							$approvaldate="";
							$approvaldate=Utils::datetimeformatter($r->ApprovalDate);
							$approvercomment=$r->ApproverComment;
							$approverhistory.="<tr>
												
												<th>$approvalsts</th>
												<th>$approvername</th>
												<th>$approvaldate</th>
												<th>$approvercomment</th>
											</tr>";
						}
						if($count>=1){
							$approverhistory.="</table>";
						}
						
						////////  Fetching Leave History //////////////
						
						$leavehistory="";
						$leavehistoryarr=$this->getEmployeeAllLeaveTypeForMail($orgid,$empid,$leavetypeid);
						foreach($leavehistoryarr as $key => $value) {
							$leavetyp=$value['name'];
							$ent=$value['days'];
							$usedleave=$value['usedleave'];
							$leftleave=$value['leftleave'];
							$carryforward=$value['carryforward'];
							$leavehistory.="
							<p><b>Leave History</b></p>
									
									<table border='1' style=' border-collapse: collapse;width:70%'>
											<tr style=' background-color: rgba(107, 58, 137, 0.91);color: rgba(255, 247, 247, 1);'>
											<th>Leave Type</th>
											<th>Carried Forward</th>
											<th>Leave Entitled</th>
											<th>Leave Utilized</th>
											<th>Balance Leave</th>
											
											</tr>
											<tr>
											<td><center>$leavetyp</center></td>
											<td><center>$carryforward</center></td>
											<td><center>$ent</center></td>
											<td><center>$usedleave</center></td>
											<td><center>$leftleave</center></td>
											
											</tr>
									</table><br>
							";
						}
						$approvelink=URL."approvalbymail/viewapproveleaveapproval/$approverid/$orgid/$leaveid/2";
						$rejectlink=URL."approvalbymail/viewapproveleaveapproval/$approverid/$orgid/$leaveid/1";
						////////////  APPROVAL LINK /////////////////
						$title="Leave approval";
						$msg="<table>
						<tr><td>Requested by: $emp_name</td></tr>
						<tr><td>Reason for leave: $leavereason</td></tr>
						<tr><td>Duration: $duration</td></tr>
						<tr><td>Leave days: $leavedays</td></tr>
						<tr><td>Resumption Date: $resumptiondate </td></tr>
						<tr><td>Substitute: $substitute</td></tr>
						<tr><td colspan='2'>														
						</td></tr>														
						</table></br>
						$leavehistory
						</br>
						$approverhistory<br>
						<table>
						<tr><td><br/><br/>
						<a href='$approvelink'   style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: green;
						-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px green; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Approve</a>
						&nbsp;&nbsp;
						&nbsp;&nbsp;
						<a href='$rejectlink'  style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: brown;
						-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px brown; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Reject</a>
						<br/><br/>
						</td>															
						</tr>	
		
					</table>";
				
					}
					//$approveremail="monika@ubitechsolutions.com";
					Utils::sendMail($approveremail,$emp_name,$title,$msg); //// sending mail to next approver
				
					
					/////////////////////////////////////////				
				}	
				if($query1->rowCount()==0)
				{
					
					$sql2 = "UPDATE EmployeeLeave SET LeaveStatus =?,ApproverComment=?,LeaveBreakDown=?, LastModifiedDate=?, LastModifiedById=?,ApprovedBy=? WHERE Id =? ";
					$query2 = $this->db->prepare($sql2);
					$query2->execute(array(2,$request[4],$request[5],$mdate,$mid,$mid, $leaveid));
					
					/*generate mail and alert for leave approved */
					Alerts::generateActionAlerts(52,$leaveid,$orgid,$this->db);
					
					
					//////////sending mail to employee ////////
					
					$leavetypeid="";
					$leavedays=0;
					$sql2 ="select * from EmployeeLeave WHERE Id = ?";
					$query2 = $this->db->prepare($sql2);
					$query2->execute(array($leaveid));
					while($r1=$query2->fetch())
					{
						
						$leavereason=$r1->LeaveReason;
						$leavefrom=date("d/m/Y", strtotime($r1->LeaveFrom));
						$leaveto=date("d/m/Y", strtotime($r1->LeaveTo));
						$fromhalfsts=$r1->FromDayType;
						$tohalfsts=$r1->ToDayType;
						$fromtypehalf=$r1->TimeOfFrom;
						$totypehalf=$r1->TimeOfTo;
						$leavetypeid=$r1->LeaveTypeId;
						$leavedays=$r1->LeaveValidDays;
					}
					$fromhalf="";
					$tohalf="";
					if($fromhalfsts==2){
						if($fromtypehalf==1){
						$fromhalf=" (1st half)";
						}else{
							$fromhalf=" (2nd half)";
						}
					}
					if($tohalfsts==2){
						if($totypehalf==1){
							$tohalf=" (1st half)";
						}else{
							$tohalf=" (2nd half)";
						}
					}
					
					$duration="";
					if($leavefrom==$leaveto){
					$duration="$leavefrom $fromhalf";	
					}else{
					$duration=" from $leavefrom $fromhalf to $leaveto $tohalf";	
					}
					
					
						$approverhistory="";
									$sql = "SELECT * FROM LeaveApproval WHERE OrganizationId = ? AND LeaveId = ? AND ApproverSts<>3 ";
									$query = $this->db->prepare($sql);
									$query->execute(array($orgid, $leaveid));
									$count =  $query->rowCount();
									if($count>=1){
										$approverhistory="<p><b>Approval History</b></p>
										<table border='1' style=' border-collapse: collapse;width:70%'>
										<tr style=' background-color: rgba(107, 58, 137, 0.91);color: rgba(255, 247, 247, 1);'>
															
															<th>Approval Status</th>
															<th>Approver</th>
															<th>Approval Date</th>
															<th>Remarks</th>
														</tr>";
									
									}
									while($r=$query->fetch()){
									

									$approvername=ucwords(Utils::getEmployeeName($r->ApproverId,$this->db));
									
										$approvalsts=$r->ApproverSts;
										
										if($approvalsts==1){
											$approvalsts="Rejected";
										}elseif($approvalsts==2){
											$approvalsts="Approved";
										}elseif($approvalsts==3){
											$approvalsts="Pending";
										}elseif($approvalsts==4){
											$approvalsts="Cancel";
										}elseif($approvalsts==5){
											$approvalsts="Withdrawn";
										}elseif($approvalsts==7){
											$approvalsts="Escalated";
										}
										$approvaldate="";
										$approvaldate=date("d/m/Y", strtotime($r->ApprovalDate));
										$approvercomment=$r->ApproverComment;
										$approverhistory.="<tr>															
															<th>$approvalsts</th>
															<th>$approvername</th>
															<th>$approvaldate</th>
															<th>$approvercomment</th>
														</tr>";
									}
									if($count>=1){
										$approverhistory.="</table>";
									}
									Utils::Trace("trace10".$leavetypeid);
									////////  Fetching Leave History //////////////
									
									$leavehistory="";
									$leavehistoryarr=$this->getEmployeeAllLeaveTypeForMail($orgid,$empid,$leavetypeid);
									foreach($leavehistoryarr as $key => $value) {
										$leavetyp=$value['name'];
										$ent=$value['days'];
										$usedleave=$value['usedleave'];
										$leftleave=$value['leftleave'];
										$carryforward=$value['carryforward'];
										$leavehistory.="
										<p><b>Leave History</b></p>
												
												<table border='1' style=' border-collapse: collapse;width:70%'>
														<tr style=' background-color: rgba(107, 58, 137, 0.91);color: rgba(255, 247, 247, 1);'>
															<th>Leave Type</th>
															<th>Carried Forward</th>
															<th>Leave Entitled</th>
															<th>Leave Utilized</th>
															<th>Balance Leave</th>
															
														</tr>
														<tr>
															<td><center>$leavetyp</center></td>
															<td><center>$carryforward</center></td>
															<td><center>$ent</center></td>
															<td><center>$usedleave</center></td>
															<td><center>$leftleave</center></td>
														
														</tr>
												</table><br>
										";
									}
								
								
					
							///////////////////// ending send mail to employee //////////////////
							$title="Application for Leave is approved";
							$msg="<p>Dear $emp_name,</p> <p> Your application for Leave has been approved.</p>";
							$msg.="<table>
										<tr><td>Reason for leave: $leavereason</td></tr>
										<tr><td>Duration: $duration</td></tr>
										<tr><td>Leave days: $leavedays</td></tr>
										</table><br>
										$leavehistory
											</br>
										$approverhistory<br>
										<table>
										<tr><td>Thanks</td></tr></table>
								";
							$sts=Utils::sendMail($emp_mail,$emp_name,$title,$msg);
							
							$sql3 = "Select * From EmployeeLeave where Id=?";
							$query3 = $this->db->prepare($sql3);
							$query3->execute(array( $leaveid ));
							while($row3 = $query3->fetch())
							{ 
								$empid=$row3->EmployeeId;
								$from=$row3->LeaveFrom;
								$to = $row3->LeaveTo;
								$fromtype = $row3->FromDayType;
								$totype = $row3->ToDayType;
								$TimeOfTo = $row3->TimeOfTo;
								$fiscal = $row3->FiscalId;
								$leaveid = $row3->Id;
								$leavebreakdown = explode(',',$row3->LeaveBreakDown);
								$entitled=$leavebreakdown[0];
								$carryforward=$leavebreakdown[1];
								$advance=$leavebreakdown[2];
								$unpaid=$leavebreakdown[3];
								Utils::Trace("tracv".$entitled);
								Utils::Trace("trac".$row3->LeaveTypeId);
								$leavecal=Utils::getName($row3->LeaveTypeId,'LeaveMaster','CarryForward',$this->db);
                      									
								$leavecal=0;					
								$i=0;
								$leavearr=$this->getLeaveDaysDifference($empid,$from,$to,$fromtype,$totype,$row3->LeaveTypeId,$TimeOfTo,$orgid);
		
								Utils::Trace("trac".$leavearr);
								for($i=0;$i<count($leavearr);$i++)
								{
									Utils::Trace("trac".$leavearr[$i]['leavedate']);
									$leavefrom=$leavearr[$i]['leavedate'];
									$halfday=0;
									if($leavearr[$i]['sts'] !=0)  /////////////CHECK HOLIDAY/WEEKOFF//////////////////
									{
										$paysts = Utils::getName($row3->LeaveTypeId, "LeaveMaster", "LeavePayRule", $this->db);
										//////This calculation belongs to leave break down//////////
										if($leavearr[$i]['sts'] ==2) ///////////////CHECK HALF DAY///////////////////
										{
											$halfday=1;
										}		
										if($entitled>0)
										{
											$e=1; $c=0; $a=0; $l=0;
											$entitled--;
											
										}
										elseif($carryforward>0 )
										{
											$e=0; $c=1; $a=0; $l=0;
											$carryforward--;
											
										}
										elseif($advance>0 )
										{
											$e=0; $c=0; $a=1; $l=0;
											$advance--;
											
										}
										elseif($unpaid>0 )
										{
											$e=0; $c=0; $a=0; $l=1;
											$unpaid--;
											
										}
										Utils::Trace("trace11".$entitled);
										$sql2 = "SELECT * from EmployeeLeaveChild  where EmployeeLeaveId =? and LeaveDay =? and LeaveTypeId=? ";
										$query2 = $this->db->prepare($sql2);
										$query2->execute(array( $leaveid, $leavefrom,  $row3->LeaveTypeId));
										if($query2->rowCount()==0){
											
											
											$sql = "INSERT INTO EmployeeLeaveChild  (EmployeeLeaveId, LeaveDay, LeaveStatus, LeaveTypeId, PaySts,HalfDaySts,Entitled, CarriedForward, Advance, LossOfPay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
											$query = $this->db->prepare($sql);
											$query->execute(array( $leaveid, $leavefrom, 2, $row3->LeaveTypeId, $paysts,$halfday,$e,$c,$a,$l));
										}else{
											
											$sql = "UPDATE EmployeeLeaveChild  set  LeaveStatus=?, PaySts=?,HalfDaySts=?, Entitled=?, CarriedForward=?, Advance=?, LossOfPay=? where EmployeeLeaveId =? and LeaveDay =? and LeaveTypeId=?";
											$query = $this->db->prepare($sql);
											$query->execute(array( 2,  $paysts,$halfday,$e,$c,$a,$l,$leaveid, $leavefrom, $row3->LeaveTypeId ));
										
										}
									}
									
								}
							}
						}
						
					   }else{  
					  
					//////////////////////if application is rejected////////////////////////////////
					$successMsg = EMPLOYEELEAVE_LEAVE_REJECT;
					$sql1 = "UPDATE EmployeeLeave SET LeaveStatus =?, ApprovedBy=? ,ApproverComment=?, LastModifiedDate=?, LastModifiedById=?  WHERE Id =?";
					$query = $this->db->prepare($sql1);
					$query->execute(array(1,$mid,$request[4],$mdate,$mid, $request[2]));
					
					/*generate mail and alert for leave request rejected */
					Alerts::generateActionAlerts(59,$leaveid,$orgid,$this->db);
					
					/////////////////////sending mail to employee /////////////////
					$leavedays=0;
					$sql2 = "select * from EmployeeLeave WHERE Id = ?";
					$query2 = $this->db->prepare($sql2);
					$query2->execute(array( $leaveid));
					while($r1=$query2->fetch())
					{
						$leavereason=$r1->LeaveReason;
						$leavefrom=date("d/m/Y", strtotime($r1->LeaveFrom));
						$leaveto=date("d/m/Y", strtotime($r1->LeaveTo));
						$fromhalfsts=$r1->FromDayType;
						$tohalfsts=$r1->ToDayType;
						$fromtypehalf=$r1->TimeOfFrom;
						$totypehalf=$r1->TimeOfTo;
						$leavetypeid=$r1->LeaveTypeId;
						$leavedays=$r1->LeaveValidDays;
					}
					$fromhalf="";
					$tohalf="";
					if($fromhalfsts==2){
						if($fromtypehalf==1){
						$fromhalf=" (1st half)";
						}else{
							$fromhalf=" (2nd half)";
						}
					}
					if($tohalfsts==2){
						if($totypehalf==1){
							$tohalf=" (1st half)";
						}else{
							$tohalf=" (2nd half)";
						}
					}
					
					$duration="";
					if($leavefrom==$leaveto){
					$duration="$leavefrom $fromhalf";	
					}else{
					$duration=" from $leavefrom $fromhalf to $leaveto $tohalf";	
					}
					
					
					$approverhistory="";
						$sql = "SELECT * FROM LeaveApproval WHERE OrganizationId = ? AND LeaveId = ? AND ApproverSts<>3 ";
						$query = $this->db->prepare($sql);
						$query->execute(array($orgid, $leaveid));
						$count =  $query->rowCount();
						if($count>=1){
							$approverhistory="<p><b>Approval History</b></p>
							<table border='1' style=' border-collapse: collapse;width:70%'>
							<tr style=' background-color: rgba(107, 58, 137, 0.91);color: rgba(255, 247, 247, 1);'>
												<th>Approval Status</th>
												<th>Approver</th>
												<th>Approval Date</th>
												<th>Remarks</th>
											</tr>";
						}
						while($r=$query->fetch()){
							$approvername=ucwords(Utils::getEmployeeName($r->ApproverId,$this->db));
							$approvalsts=$r->ApproverSts;
							if($approvalsts==1){
								$approvalsts="Rejected";
							}elseif($approvalsts==2){
								$approvalsts="Approved";
							}elseif($approvalsts==3){
								$approvalsts="Pending";
							}elseif($approvalsts==4){
								$approvalsts="Cancel";
							}elseif($approvalsts==5){
								$approvalsts="Withdrawn";
							}elseif($approvalsts==7){
								$approvalsts="Escalated";
							}
							$approvaldate="";
							$approvaldate=Utils::datetimeformatter($r->ApprovalDate);
							$approvercomment=$r->ApproverComment;
							$approverhistory.="<tr>															
												<th>$approvalsts</th>															
												<th>$approvername</th>
												<th>$approvaldate</th>
												<th>$approvercomment</th>
											</tr>";
						}
						if($count>=1){
							$approverhistory.="</table>";
						}
						
						////////  Fetching Leave History //////////////
						
						$leavehistory="";
						$leavehistoryarr=$this->getEmployeeAllLeaveTypeForMail($orgid,$empid,$leavetypeid);
						foreach($leavehistoryarr as $key => $value) {
							$leavetyp=$value['name'];
							$ent=$value['days'];
							$usedleave=$value['usedleave'];
							$leftleave=$value['leftleave'];
							$carryforward=$value['carryforward'];
							$leavehistory.="
							<p><b>Leave History</b></p>
									
									<table border='1' style=' border-collapse: collapse;width:70%'>
											<tr style=' background-color: rgba(107, 58, 137, 0.91);color: rgba(255, 247, 247, 1);'>
												<th>Leave Type</th>
												<th>Carried Forward</th>
												<th>Leave Entitled</th>
												<th>Leave Utilized</th>
												<th>Balance Leave</th>
												
											</tr>
											<tr>
												<td><center>$leavetyp</center></td>
												<td><center>$carryforward</center></td>
												<td><center>$ent</center></td>
												<td><center>$usedleave</center></td>
												<td><center>$leftleave</center></td>
												
											</tr>
									</table><br>
							";
						}
					
					
						$title="Application for Leave is rejected";
						
						$msg="<p>Dear $emp_name,</p> <p> Your application for Leave has been rejected.</p><br>";
							$msg.="<table>
										<tr><td>Reason for leave: $leavereason</td></tr>
										<tr><td>Duration: $duration</td></tr>
										<tr><td>Leave days: $leavedays</td></tr>
										</table>
										$leavehistory
											</br>
										$approverhistory<br>
										<table>
										<tr><td>Thanks</td></tr></table>
								";
								//$emp_mail="monika@ubitechsolutions.com";
						$sts=Utils::sendMail($emp_mail,$emp_name,$title,$msg);
						
						Utils::Trace($emp_mail." ".$title." ".$msg);
			   }
			  
			} else {
			   $status =false;
			   	$sql1 = "select * from LeaveApproval WHERE LeaveId = ? and OrganizationId=?";
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array( $leaveid, $orgid));
				$con=$query1->rowCount();
				$ApproverSts="Marked";
				if($r=$query1->fetch()){
					$ApproverSts=$r->ApproverSts;
				}
				if($ApproverSts==1){
					$ApproverSts="Rejected";
				}
				if($ApproverSts==2){
					$ApproverSts="Approved";
				}if($ApproverSts==7){
					$ApproverSts="Escalated";
				}
				
			   $errorMsg = "Leave already has been $ApproverSts";
			}
		}catch(Exception $e) {
			$errorMsg = 'Message: ' .$e->getMessage();
		}
		
		$result["data"] =$data;
		$result['status']=$status;
		$result['successMsg']=$successMsg;
		$result['errorMsg']=$errorMsg;
		
        return $result;
    }
	
	public function getEmployeeAllLeaveTypeForMail($orgid,$mid,$leavetypeid) {
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		$applydate=date('Y-m-d');
		$division=0;$department=0; $designation=0; $grade=0; $gender=0; $marital=0; $religion=0; $workingdays=0;
		$divisionflg=false;$departmentflg=false; $designationflg=false; $gradeflg=false; $genderflg=false; $maritalflg=false; $religionflg=false; $halfdays=0;
		$doj=date('Y-m-d');
		$mdate=date('Y-m-d');
		try{
		//$fiscalid = Utils::getFiscalId($applydate, $this->db);
		$fiscalid = Utils::getFiscalIdForApp($orgid,$applydate, $this->db);
		$sql1 = "SELECT *  FROM FiscalMaster WHERE Id=?";
		$query1 = $this->db->prepare($sql1);
		$query1->execute(array( $fiscalid));
		while($row1=$query1->fetch()){
			$actualstartdate=$startdate=$row1->StartDate;
			$actualenddate=$enddate=$row1->EndDate;
		}
		
		
		$annualid =0; $annualcount =0;
		$sql = "SELECT MaritalStatus, Gender, Division, Department, Designation, Grade, Religion, TotalExp,WorkingDays,DOJ,Shift,ProvisionPeriod FROM EmployeeMaster WHERE OrganizationId = ? and Id =?";
        $query = $this->db->prepare($sql);
		$query->execute(array( $orgid, $mid));
		while($row = $query->fetch())
		{
			$division=$row->Division;
			$department=$row->Department; 
			$designation=$row->Designation; 
			$grade=$row->Grade; 
			$religion=$row->Religion; 
			$gender=$row->Gender; 
			$marital=$row->MaritalStatus;
			$doj=$row->DOJ;
			$ProvisionPeriod=$row->ProvisionPeriod;
			$date1= date("Y-m-d", strtotime("+".$ProvisionPeriod." month ".date($doj)));
			$currdate = date("Y-m-d");
		}
		
        $sql = "SELECT * FROM LeaveMaster WHERE OrganizationId = ? and Id =?";
        $query = $this->db->prepare($sql);
		try{
			
			$query->execute(array($orgid, $leavetypeid));
			$count =  $query->rowCount();
		}catch(Exception $e) {
			$errorMsg = 'Message: ' .$e->getMessage();
		}
		if($count>=1)
		{
			$status=true;
			$successMsg=$count." record found";
			
			while($row = $query->fetch())
			{
				$startdate=$actualstartdate;
				$enddate=$actualenddate;	
				$divisionflg=true;
				$departmentflg=true; 
				$designationflg=true; 
				$gradeflg=true; $religionflg=true;
				$genderflg=true; $maritalflg=true;
				$probationsts=true;
				
				$carryforward=0;
				$employeeusedleave=0;
				$days_between=365;
				$leavealotted=$row->LeaveDays;
				
				$leaveeffectivedate=$row->LeaveApply;
				$startdate1=$startdate;
				if($row->Period==2){
					if((strtotime($leaveeffectivedate) > strtotime($startdate)) &&(strtotime($leaveeffectivedate) < strtotime($enddate)) ){
						//$startdate=$leaveeffectivedate;
						
						$startdate1=$leaveeffectivedate;
						$start = strtotime($leaveeffectivedate);
						$end = strtotime($enddate);
						$days_between = (abs($end - $start) / 86400);
						/* $leavealotted1=ceil(($days_between * $leavealotted)/365);
						$leavealotted=$leavealotted1; */
					}
					if($row->ProbationSts==1){	
						if((strtotime($doj) > strtotime($startdate1)) ){
						$start = strtotime($doj);
						$end = strtotime($enddate);
						$days_between = (abs($end - $start) / 86400);
						}
					}
					else{	
						if((strtotime($date1) > strtotime($startdate1)) ){
						$start = strtotime($date1);
						$end = strtotime($enddate);
						$days_between = (abs($end - $start) / 86400);
						}
					}
					$leavealotted1=ceil(($days_between * $leavealotted)/365);
					//echo "actual alloted $leavealotted <br>";
					$leavealotted=$leavealotted1;
				}
				
				////////////////IF LEAVE IS ON MONTHLY BASIS////////////
				
				if($row->Period==1)
				{
					$days_between =30;
					$startdate=date('Y-m-1',strtotime(date($applydate)));
					$enddate=date("Y-m-t", strtotime(date($applydate)));
					$startdate1=$startdate;
					if((strtotime($leaveeffectivedate) > strtotime($startdate)) &&(strtotime($leaveeffectivedate) < strtotime($enddate)) ){
							//$startdate=$leaveeffectivedate;
							$startdate1=$leaveeffectivedate;
							$start = strtotime($leaveeffectivedate);
							$end = strtotime($enddate);
							$days_between = (abs($end - $start) / 86400);
					}

					if($row->ProbationSts==1){
						if((strtotime($doj) > strtotime($startdate1)) ){
							$start = strtotime($doj);
							$end = strtotime($enddate);
							$days_between = (abs($end - $start) / 86400);
						
						} 
					}
					else{
						if((strtotime($date1) > strtotime($startdate1)) ){
							$start = strtotime($date1);
							$end = strtotime($enddate);
							$days_between = (abs($end - $start) / 86400);
						
						} 
					}
					
					$leavealotted1=ceil(($days_between * $leavealotted)/30);
					$leavealotted=$leavealotted1;
					
				}
				
				////////////CHECK IF LEAVE TYPE IS FOR SPECIFIC EMPLOYEES/////////////
				
				if($row->LeaveUsableSts==2){
					$empsts=false;
					if($row->EmployeeIds!="")
					{
						$temp = explode(",", $row->EmployeeIds);
						for($i=0; $i<count($temp); $i++)
						{
							if($mid==$temp[$i]){
								$empsts=true;
								break;
							}
							
						}
					}
					if(!$empsts)
					{
						if($row->DivisionId>0){
							if($row->DivisionId==$division){
								$divisionflg=true;
							}else{$divisionflg=false;}
						}
						if($row->DepartmentIds>0){
							if($row->DepartmentIds==$department){
								$departmentflg=true; 
							}else{$departmentflg=false; }
						}
						if($row->DesignationIds>0){
							if($row->DesignationIds==$designation){
								$designationflg=true; 
							}else{$designationflg=false; }
						}
						if($row->GenderId>0){
							if($row->GenderId==$gender){
								$genderflg=true;
							}else{$genderflg=false;}
						}
						if($row->MaritalId>0){
							if($row->MaritalId==$marital){
								$maritalflg=true;
							}else{$maritalflg=false;}
						}
						if($row->GradeId>0){
							if($row->GradeId==$grade){
								$gradeflg=true; 
							}else{$gradeflg=false; }
						}
						if($row->ReligionId>0){
							if($row->ReligionId==$religion){
								$religionflg=true; 
							}else{$religionflg=false; }
						}
						
						///////////////////IF PROBATION STATUS IS 1 THEN LEAVE IS APPLICATION FOR ALL OTHERWISE WE HAVE TO CHECK PROBATION ENDING OF EMPLOYEE/////////////////////
						
						if($row->ProbationSts==0)
						{
							if($currdate  > $date1){
								$probationsts=true;
							}else{$probationsts=false;}
						}
					}
				}
				
				if($divisionflg && $departmentflg && $designationflg && $gradeflg && $genderflg && $maritalflg && $religionflg && $probationsts)
				{
					$sql3 = "Select * from EmployeeLeaveChild as empchild,EmployeeLeave as empleave where empchild.EmployeeLeaveId=empleave.Id and empchild.LossOfPay=0 and empchild.LeaveStatus=2 and (empchild.LeaveDay between '$startdate' and '$enddate') and empleave.OrganizationId = '$orgid' and empleave.EmployeeId =$mid and empleave.LeaveTypeId=$row->Id and empleave.LeaveStatus=2 and empchild.Entitled=1";
					$query3 = $this->db->prepare($sql3);
					$query3->execute();
					while($row3=$query3->fetch()){
						if($row3->HalfDaySts == 1){
							$employeeusedleave = $employeeusedleave + 0.5;
						}
						else{
							$employeeusedleave++;
						}
					}
					//////////this code belongs to advance leave ,if there are advance leave in previous year /month taken by employee we have to add it to employee current used leave////////
					
					$strdateadv=date('Y-m-d',strtotime('-1 year', strtotime(date($startdate))));
					$enddateadv=date("Y-m-d", strtotime('-1 year', strtotime(date($enddate))));
					if($row->Period==1)
					{
						$strdateadv=date('Y-m-d',strtotime('-1 month', strtotime(date($startdate))));
						$enddateadv=date("Y-m-d", strtotime('-1 month', strtotime(date($enddate))));
					}	
					
					$sql3 = "Select Advance,HalfDaySts from EmployeeLeaveChild as empchild,EmployeeLeave as empleave where empchild.EmployeeLeaveId=empleave.Id and empchild.LeaveStatus=2 and (empchild.LeaveDay between ? and ?) and empleave.OrganizationId = ? and empleave.EmployeeId =? and empleave.LeaveTypeId=? and empleave.LeaveStatus=2 and empchild.Advance=1 and empchild.LossOfPay=0";
					$query3 = $this->db->prepare($sql3);
					$query3->execute(array($strdateadv, $enddateadv, $orgid, $mid, $row->Id));
					while($row3=$query3->fetch()){
						if( $row3->HalfDaySts == 1){
							$employeeusedleave = $employeeusedleave + 0.5;
						}
						else{
							$employeeusedleave++;
						}
					}
					
					$leftleave = $leavealotted-$employeeusedleave;
					
					////////////////FIND OUT LEAVE BALANCE OF LAST YEAR/MONTH/////////////
					
					if($row->carriedforward==1){
						$carryforward = $this->getCarryforwardleave($mid,$fiscalid,$orgid,$row->Id);
					}	
					
					if($leftleave>=0){
						$res = array();
						$res['id'] = (int)$row->Id;
						$res['name'] = $row->Name;
						$res['days'] = (int)$leavealotted;
						$res['usedleave'] = $employeeusedleave;
						$res['leftleave'] = $leftleave; ////////////////THIS YEAR BALANCE//////////
						$res['carryforward'] = $carryforward;  ////////////////LAST YEAR BALANCE//////////////
						$res['totalleave'] = $leftleave+$carryforward; ////////////////TOTAL LEAVE BALANCE FOR THIS YEAR///////////////////////////
						$res['period'] = ($row->Period==1)?"Month":"Year";
						$res['compoffsts'] = (int) $row->compoffsts;
						$res['workfromhomests'] =  (int)$row->workfromhomests;
						$data[] = $res;
					}
				}
			}	
        }else{
			$status=true;
			$successMsg = LEAVETYPE_MODULE_GETALL;
		}
		}catch(Exception $e) {
			$status=false;
			$errorMsg = 'Message: ' .$e->getMessage();
		}
		return $data;
    }
	
	public function getLeaveDaysDifference($id,$from,$to,$fromtype,$totype,$LeaveTypeId,$TimeOfTo,$orgid)
	{
		$leavefrom       = $from;
		$leaveto         = $to;
		$leavefromtype   = $fromtype;
		$leavetotype     = $totype;
		$leavetimeofto   = $TimeOfTo;
		$resumptiondate  ="";
		$orgid           = $orgid;
		$leavetype       = $LeaveTypeId;
		//Utils::Trace("in function".$from.$to.$fromtype.$totype);
		$leavecalculetedby=Utils::getName($leavetype,'LeaveMaster','CarryForward',$this->db);
		$includeweekoffsts=Utils::getName($leavetype,'LeaveMaster','includeweekoff',$this->db);
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		$total =0; $weekoff=0;$labeltype="";
		Utils::Trace("in function0".$includeweekoffsts.$leavecalculetedby);
		if($leavefrom!="" && $leaveto!=""){
			$resumptiondate = date('Y-m-d', strtotime('+1 day', strtotime(date($leaveto))));
			Utils::Trace("in function1".$leavefrom.$leaveto);
			$status=true;
			while(date($leavefrom)<=date($leaveto)){
				//Utils::Trace("in function2".$leavefrom.$leaveto);
				$j=0;	
				$div=Utils::getName($id,'EmployeeMaster','Division',$this->db);
				$sql = "SELECT Id, Name,DateFrom FROM HolidayMaster WHERE OrganizationId=? and (? between DateFrom and DateTo)  and FIND_IN_SET($div,DivisionId)";
				$query = $this->db->prepare($sql);
				try{
					$query->execute(array($orgid,$leavefrom ));
					if($query->rowCount()>0){
						//Utils::Trace("in function3".$leavefrom);
						while($row = $query->fetch()){
							//Utils::Trace("in function4".$row->Name);
							$j++;
							$weekoff++;
							$res = array();
							$res['date'] = date( "l, d F, Y", strtotime(date($leavefrom)));
							$res['leavedate'] =$leavefrom;
							$res['label'] = "Holiday -".$row->Name;
							$res['labeltype'] = "Holiday";

							$res['sts'] = 0;
							$data[] = $res;
						}
					}else{
						//Utils::Trace("in function5".$leavefromtype);
						$sts=1;
						//$temparr = explode(',',$workingday);
						$dw = date( "w", strtotime(date($leavefrom)));
						if($leavefromtype==2 && $total==0 ){
							$date = "Leave Half Day";
							$labeltype = "Half Day";
							$sts=2;
							//$total=$total+0.5;
						}elseif($leavetotype==2 && (date($leavefrom)==date($leaveto)) ){
							$date = "Leave Half Day";
							$labeltype = "Half Day";
							$sts=2;
							//$total=$total+0.5;
						}else{
							$date = "Leave Full Day";
							$labeltype = "Leave";
							$sts=1;
							//$total++;
						}	
						if($leavetotype==2 && $leavefromtype==2 && (date($leavefrom)==date($leaveto)) ){
							if($leavetimeofto==2){
								$resumptiondate = date('Y-m-d', strtotime('+1 day', strtotime(date($leaveto))));
							}else{
								$resumptiondate = date('Y-m-d', strtotime(date($leaveto)));
							}
						}elseif($leavetotype==2 && $leavefromtype==1 && (date($leavefrom)<date($leaveto)) ){
							if($leavetimeofto==1){
								$resumptiondate = date('Y-m-d', strtotime(date($leaveto)));
							}else{
								$resumptiondate = date('Y-m-d', strtotime('+1 day', strtotime(date($leaveto))));
							}
						}elseif($leavetotype==1 && $leavefromtype==2 && (date($leavefrom)<date($leaveto)) ){
							$resumptiondate = date('Y-m-d', strtotime('+1 day', strtotime(date($leaveto))));
						}elseif($leavetotype==1 && $leavefromtype==1 && (date($leavefrom)<=date($leaveto)) ){ 
							$resumptiondate = date('Y-m-d', strtotime('+1 day', strtotime(date($leaveto))));
						}
									
						/////// gettung weekly off from shift of employee
						//Utils::Trace("in function5".$leavefrom);
						$weekno=Utils::weekOfMonth($leavefrom);
						$dayofdate= 1 + date("w", strtotime($leavefrom));
						//Utils::Trace("in function6".$weekno.$leavefrom);
						$sql2 = "SELECT WeekOff FROM ShiftMasterChild where ShiftId=(select shift from EmployeeMaster where Id=$id) and Day=$dayofdate";
						
						//Utils::Trace($sql2." Date used - ".$leavefrom." day of month -  ".$dayofdate." week of month - ".$weekno);
						$query2 = $this->db->prepare($sql2);
						$query2->execute(array());
						$week="";
						if($row2 = $query2->fetch()){
							$week=$row2->WeekOff;
						}
						$weekarr=explode(",",$week);
						if($query2->rowCount()>0){
							 Utils::Trace("in function6 in if week ".$leavefrom);
							if($weekarr[$weekno-1]==1){
								$weekoff++;
								$j++;
								$date = " Weekly off  -".date( "l", strtotime(date($leavefrom)));
								$labeltype = "Weekly off";
								$sts=0;
							}else if($weekarr[$weekno-1]==2){
								$weekoff = $weekoff +0.5;
								$date = " HalfDay -".date( "l", strtotime(date($leavefrom)));
								$labeltype = "HalfDay";
								$sts=2;
							}
						}
						$res = array();
						$res['date'] = date( "l, d F, Y", strtotime(date($leavefrom)));
						$res['leavedate'] =$leavefrom;
						$res['label'] = $date;
						$res['labeltype'] = $labeltype;
						$res['sts'] = $sts;
						$data[] = $res;
						//Utils::Trace("in functi data".$leavefrom.$sts);
					}
					
					if($leavefromtype==2 && $total==0 && $j==0){
						$total=$total+0.5;
					}
					elseif($leavetotype==2 && (date($leavefrom)==date($leaveto)) && $j==0){
						$total=$total+0.5;
					}elseif($j==0){
						$total++;
					}
				}catch(Exception $e) {
					
				}
				$leavefrom = date('Y-m-d', strtotime('+1 day', strtotime(date($leavefrom))));
			}
			
			if($includeweekoffsts){$c=0;
				if(($data[0]['labeltype']=='Leave') && ($data[(sizeof($data) - 1)]['labeltype']=='Leave')){//echo "a";
					$c++;
				}
				if($c>0){
					$total=$total+$weekoff;
				}
			}
		}
		
		//Utils::Trace("in funct".$total);
	
		if($leaveto!="" && $resumptiondate!="" ){
			//Utils::Trace("in funct22 --".$leaveto);
			$status=true;
			while(date($leaveto)<=date($resumptiondate)){
				//Utils::Trace("in funct23 **".$leaveto);
				$h=0;	
				$div1=Utils::getName($id,'EmployeeMaster','Division',$this->db);
				
				$sql1 = "SELECT Id, Name FROM HolidayMaster WHERE OrganizationId=? and ? between DateFrom and DateTo and FIND_IN_SET($div1,DivisionId)";
				
				$query = $this->db->prepare($sql1);
				try{
					$query->execute(array($orgid,$resumptiondate ));
					if($query->rowCount()>0){
						while($row = $query->fetch()){
							$h++;
							$weekoff++;
							$resumptiondate = date('Y-m-d', strtotime('+1 day', strtotime(date($resumptiondate))));
						}
					}else{
						//Utils::Trace("in funct II".$resumptiondate);
						/////// getting weekly off from shift of employee
						$weekno=Utils::weekOfMonth($resumptiondate);
						$dayofdate= 1 + date("w", strtotime($resumptiondate));
						
						$sql2 = "SELECT WeekOff FROM ShiftMasterChild where ShiftId=(select shift from EmployeeMaster where Id=$id) and Day=$dayofdate";
						
						$query2 = $this->db->prepare($sql2);
						$query2->execute(array());
						
						$week="";
						if($row2 = $query2->fetch()){
							$week=$row2->WeekOff;
						}
						$weekarr=explode(",",$week);
						if($query2->rowCount()>0){
							if($weekarr[$weekno-1]==1){
								$weekoff++;
								$h++;
								$date = " Weekly off  -".date( "l", strtotime(date($resumptiondate)));
							$resumptiondate = date('Y-m-d', strtotime('+1 day', strtotime(date($resumptiondate))));
						
							}else if($weekarr[$weekno-1]==2){
								$weekoff = $weekoff +0.5;
								$date = " HalfDay -".date( "l", strtotime(date($resumptiondate)));
							$resumptiondate = date('Y-m-d', strtotime(date($resumptiondate)));

							}
						}
					}
				}catch(Exception $e) {
					
				}
				$leaveto = date('Y-m-d', strtotime('+1 day', strtotime(date($leaveto))));
			}
		}
		return $data;
	}
	
	public function getPreviousyearFiscalId($orgid,$db)
	{
		$id =0;
		$sql = "SELECT * FROM `FiscalMaster` WHERE `OrganizationId`=$orgid and FiscalSts<>1 ORDER BY ID DESC Limit 1";
        $query = $db->prepare($sql);
		try{
			$query->execute();
			while($row = $query->fetch()){
				$id = $row->Id;
			}
		}catch(Exception $e) {
			
		}
		return $id;
	}
	
	public function getEmployeeAllLeaveType($request)
    {
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; 
		$status=false;
		$data = array();
		$empid   =$request[0];	//USER ID CONTAINS IN ARRAY FIRST VALUE;
		$orgid = $request[1];	//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$applydate = date('Y-m-d');	
		$division=0;$department=0; $designation=0; $grade=0; $gender=0; $marital=0; $religion=0;$EmployeeExperience='';
		$emp_type=0;
		$divisionflg=false;$departmentflg=false; $designationflg=false; $gradeflg=false; $genderflg=false; $maritalflg=false; $religionflg=false;$experienceflg=false; $halfdays=0;
		$emptypeflg=false;
		$fiscaldata=array();
		$doj=date('Y-m-d');
		$mdate=date('Y-m-d');
		$actualstartdate=$startdate=date('Y-04-01');
		$actualenddate=$enddate=date('Y-03-31');
		
		try{
			$preyearfiscalid = $this->getPreviousyearFiscalId($orgid, $this->db);
			$fiscalid = Utils::getFiscalIdForApp($orgid,$applydate, $this->db);
			
			$sql1 = "SELECT *  FROM FiscalMaster WHERE Id=?";
			$query1 = $this->db->prepare($sql1);
			$query1->execute(array( $fiscalid));
			while($row1=$query1->fetch()){
				$res1=array();
				$res1['id']=$row1->Id;
				$res1['name']=$row1->Name;
				$actualstartdate=$startdate=$row1->StartDate;
				$actualenddate=$enddate=$row1->EndDate;
				$res1['startdate']=Utils::dateformatter($row1->StartDate);
				$res1['enddate']=Utils::dateformatter($row1->EndDate);
				$fiscaldata[]=$res1;
			}
			
			$sql = "SELECT MaritalStatus, Gender, Division, Department, Designation, Grade, Religion, TotalExp,WorkingDays,DOJ,Shift,ProvisionPeriod,EmploymentType,TIMESTAMPDIFF(YEAR, DOJ, CURDATE()) as curyear, TIMESTAMPDIFF(MONTH, DOJ, CURDATE()) as curmonth FROM EmployeeMaster WHERE OrganizationId = ? and Id =? and Is_Delete=0";
			$query = $this->db->prepare($sql);
			$query->execute(array($orgid, $empid));
			while($row = $query->fetch())
			{
				$curyear=0;$curmonth=0;
				$division=$row->Division;
				$department=$row->Department; 
				$designation=$row->Designation; 
				$grade=$row->Grade; 
				$religion=$row->Religion; 
				$gender=$row->Gender; 
				$marital=$row->MaritalStatus;
				$doj=$row->DOJ;
				$ProvisionPeriod=$row->ProvisionPeriod;
				$emp_type=$row->EmploymentType;
				$date1= date("Y-m-d", strtotime("+".$ProvisionPeriod." month ".date($doj)));
				$currdate = date("Y-m-d");
			
				$curyear = ((int)$row->curyear < 0)?0:(int)$row->curyear;
				$curmonth = ((int)$row->curmonth < 0)?0:(int)$row->curmonth;
				$year=($curyear * 12);
				$curmonth=(int)($curmonth - $year);
			}
			
			$sql = "SELECT * FROM LeaveMaster WHERE OrganizationId = ? and LeaveApply <= CURDATE() and VisibleSts=1";
			$query = $this->db->prepare($sql);
			try{
				$query->execute(array($orgid));
				$count =  $query->rowCount();
			}catch(Exception $e) {
				$errorMsg = 'Message: ' .$e->getMessage();
			}
			if($count>=1)
			{
				$status=true;
				$successMsg=$count." record found";
				
				while($row = $query->fetch()){	
					if($row->compoffsts==0){
						$startdate=$actualstartdate;
						$enddate=$actualenddate;	
						$divisionflg=true;
						$departmentflg=true; 
						$designationflg=true; 
						$gradeflg=true; 
						$religionflg=true; 
						$genderflg=true;
						$emptypeflg=true;
						$maritalflg=true;
						$probationsts=true;
						$experienceflg=true;
						$y=0;$m=0;
						
						$preCFleave=0;
						$leavealotted1=0;
						$totalcreditleave=0;
						$employeeusedleave=0;
						$totalutilizedleave=0;
						$cfrleave=0;
						$balanceleave=0;
						$days_between=365;
						$creditleavepermonth=$row->Monthleave;
						$carriedforward=$row->carriedforward;
						$cappingsts=$row->Caping;
						$leavealotted=$row->LeaveDays;
						$workfromhomests=$row->workfromhomests;
						if($row->Caping==1)
						{
							$leavealotted=round(($row->LeaveDays/12),1);
						}
						$leaveeffectivedate=$row->LeaveApply;
						$startdate1=$startdate;
						
						if($row->EmployeeExperience!=""){
							$exp = explode(',',$row->EmployeeExperience);
							$y=(int)$exp[0];
							$m=(int)$exp[1];
						}
						$n='';
						$n=Utils::getName($row->Id,'LeaveMaster','Name',$this->db);
						$emp_typearr=($row->EmploymentType!=0)?explode(",",$row->EmploymentType):0;
						////////////CHECK IF LEAVE TYPE IS FOR SPECIFIC EMPLOYEES/////////////
						if($row->LeaveUsableSts==1){
							if($row->ProbationSts==0){
								if($currdate  > $date1){
									$probationsts=true;
								}else{$probationsts=false;}
							}
						}
						if($row->LeaveUsableSts==2){
							$empsts=false;
								if($row->EmployeeIds!=""){
									$divisionflg=false;
									$departmentflg=false; 
									$designationflg=false; 
									$gradeflg=false;
									$religionflg=false;
									$genderflg=false; 
									$emptypeflg=false;
									$maritalflg=false;
									$probationsts=false;
									$experienceflg=false;
									
									$temp = explode(",", $row->EmployeeIds);
									for($i=0; $i<count($temp); $i++){
										if($empid==$temp[$i]){ 
											$divisionflg=true;
											$departmentflg=true; 
											$designationflg=true; 
											$gradeflg=true; 
											$religionflg=true;
											$genderflg=true;
											$emptypeflg=true;
											$maritalflg=true;
											$probationsts=true;
											$experienceflg=true;
											$empsts=true;
											if($row->ProbationSts==0){
												if($currdate  > $date1){
													$probationsts=true;
												}else{$probationsts=false;}
											}
											break;
										}
										
									}
								}
								elseif(!$empsts)
								{
									if($row->DivisionId>0){
											if($row->DivisionId==$division){
												$divisionflg=true;
											}else{$divisionflg=false;}
									}
									if($row->DepartmentIds>0){
										if($row->DepartmentIds==$department){
												$departmentflg=true; 
										}else{$departmentflg=false; }
									}
									if($row->DesignationIds>0){
										if($row->DesignationIds==$designation){
												$designationflg=true; 
										}else{$designationflg=false; }
									}
									if($row->GenderId>0){
										if($row->GenderId==$gender){
											$genderflg=true;
										}else{$genderflg=false;}
									}
									if($emp_typearr==0){ 
											$emptypeflg=true;	
									}elseif( in_array($emp_type, $emp_typearr ) ){
											$emptypeflg=true;
									}else{
										$emptypeflg=false;
									}
									if($row->MaritalId>0){
										if($row->MaritalId==$marital){
											$maritalflg=true;
										}else{$maritalflg=false;}
									}
									if($row->GradeId>0){
										if($row->GradeId==$grade){
											$gradeflg=true; 
										}else{$gradeflg=false; }
									}
									if($row->ReligionId>0){
										if($row->ReligionId==$religion){
											$religionflg=true; 
										}else{$religionflg=false; }
									}
									if(($row->EmployeeExperience!='0,0') || ($row->EmployeeExperience!='')){
										if(($y!='') || ($m!='')){//echo $curyear." ".$y;
											if($curyear >= $y){//echo $curmonth." ".$m;
												if($curmonth >=  $m){
												$experienceflg=true; 
												}elseif(($curmonth <  $m) && ($curyear > $y)){
													$experienceflg=true; 
												}else{$experienceflg=false; }
											}elseif(($curmonth >=  $m) && ($curyear >= $y)){
												$experienceflg=true; 
											}else{$experienceflg=false; }
										}
									}
										
									///////////////////IF PROBATION STATUS IS 1 THEN LEAVE IS APPLICATION FOR ALL/////////////////////
									
									if($row->ProbationSts==0){	
										if(strtotime($currdate)  > strtotime($date1)){
											$probationsts=true;
										}else{$probationsts=false;}
									}
								}
						}
						
						if($divisionflg && $departmentflg && $designationflg && $gradeflg && $genderflg && $maritalflg && $religionflg && $probationsts && $experienceflg && $emptypeflg)
						{
							if((strtotime($leaveeffectivedate) >= strtotime($startdate)) &&(strtotime($leaveeffectivedate) < strtotime($enddate)) ){
								if((strtotime($date1) < strtotime($leaveeffectivedate)) ){
									if($row->Caping==1){
										$start=date('Y' ,strtotime($leaveeffectivedate));
										$end = date('Y' , strtotime($enddate));
										$Y1=date('m',strtotime($leaveeffectivedate));
										$M1=date('m',strtotime($enddate));
										$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
										$leavealotted1=($diff1+1) * $leavealotted;
										
										$countmonth=date('Y' ,strtotime($leaveeffectivedate));
										$currentdate = date('Y' , strtotime(date("Y-m-d")));
										$Y=date('m',strtotime($leaveeffectivedate));
										$M=date('m',strtotime(date("Y-m-d")));
										$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
										$totalcreditleave=($diff+1) * $creditleavepermonth;
									}else{
										$start = strtotime($leaveeffectivedate);
										$end = strtotime($enddate);
										$days_between = (abs($end - $start) / 86400);
										$leavealotted1=round(($days_between * $leavealotted)/365);
										$totalcreditleave=$leavealotted1;
									}
								}else{
									if($row->ProbationSts==1){
										if((strtotime($doj) <= strtotime($leaveeffectivedate)) ){
											if($row->Caping==1){
												$start=date('Y' ,strtotime($leaveeffectivedate));
												$end = date('Y' , strtotime($enddate));
												$Y1=date('m',strtotime($leaveeffectivedate));
												$M1=date('m',strtotime($enddate));
												$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
												$leavealotted1=($diff1+1) * $leavealotted;
												
												$countmonth=date('Y' ,strtotime($leaveeffectivedate));
												$currentdate = date('Y' , strtotime(date("Y-m-d")));
												$Y=date('m',strtotime($leaveeffectivedate));
												$M=date('m',strtotime(date("Y-m-d")));
												$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
												$totalcreditleave=($diff+1) * $creditleavepermonth;
											}else{
												$start = strtotime($leaveeffectivedate);
												$end = strtotime($enddate);
												$days_between = (abs($end - $start) / 86400);
												$leavealotted1=round(($days_between * $leavealotted)/365);
												$totalcreditleave=$leavealotted1;
											}
										}else{
											if($row->Caping==1){
												$start=date('Y' ,strtotime($doj));
												$end = date('Y' , strtotime($enddate));
												$Y1=date('m',strtotime($doj));
												$M1=date('m',strtotime($enddate));
												$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
												$leavealotted1=($diff1+1) * $leavealotted;
												
												$countmonth=date('Y' ,strtotime($doj));
												$currentdate = date('Y' , strtotime(date("Y-m-d")));
												$Y=date('m',strtotime($doj));
												$M=date('m',strtotime(date("Y-m-d")));
												$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
												$totalcreditleave=($diff+1) * $creditleavepermonth;
											}else{
												$start = strtotime($doj);
												$end = strtotime($enddate);
												$days_between = (abs($end - $start) / 86400);
												$leavealotted1=round(($days_between * $leavealotted)/365);
												
												$totalcreditleave=$leavealotted1;
											}
										}
									}else{
										if($row->Caping==1){
											$start=date('Y' ,strtotime($date1));
											$end = date('Y' , strtotime($enddate));
											$Y1=date('m',strtotime($date1));
											$M1=date('m',strtotime($enddate));
											$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
											$leavealotted1=($diff1+1) * $leavealotted;
											
											$countmonth=date('Y' ,strtotime($date1));
											$currentdate = date('Y' , strtotime(date("Y-m-d")));
											$Y=date('m',strtotime($date1));
											$M=date('m',strtotime(date("Y-m-d")));
											$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
											$totalcreditleave=($diff+1) * $creditleavepermonth;
										}else{
											$start = strtotime($date1);
											$end = strtotime($enddate);
											$days_between = (abs($end - $start) / 86400);
											$leavealotted1=round(($days_between * $leavealotted)/365);
											
											$totalcreditleave=$leavealotted1;
										}
									}
								}
							}
							
							if(strtotime($leaveeffectivedate) < strtotime($startdate)){
								if((strtotime($date1) < strtotime($startdate1)) ){
									if($row->Caping==1){
										$start=date('Y' ,strtotime($startdate1));
										$end = date('Y' , strtotime($enddate));
										$Y1=date('m',strtotime($startdate1));
										$M1=date('m',strtotime($enddate));
										$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
										$leavealotted1=($diff1+1) * $leavealotted;
										
										$countmonth=date('Y' ,strtotime($startdate1));
										$currentdate = date('Y' , strtotime(date("Y-m-d")));
										$Y=date('m',strtotime($startdate1));
										$M=date('m',strtotime(date("Y-m-d")));
										$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
										$totalcreditleave=($diff+1) * $creditleavepermonth;
									}else{
										$start = strtotime($startdate1);
										$end = strtotime($enddate);
										$days_between = (abs($end - $start) / 86400);
										$leavealotted1=round(($days_between * $leavealotted)/365);
										
										$totalcreditleave=$leavealotted1;
									}
								}
								else{
									if($row->ProbationSts==1){
										if(strtotime($doj) < strtotime($startdate) ){
											if($row->Caping==1){
												$start=date('Y' ,strtotime($startdate1));
												$end = date('Y' , strtotime($enddate));
												$Y1=date('m',strtotime($startdate1));
												$M1=date('m',strtotime($enddate));
												$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
												$leavealotted1=($diff1+1) * $leavealotted;
												
												$countmonth=date('Y' ,strtotime($startdate1));
												$currentdate = date('Y' , strtotime(date("Y-m-d")));
												$Y=date('m',strtotime($startdate1));
												$M=date('m',strtotime(date("Y-m-d")));
												$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
												$totalcreditleave=($diff+1) * $creditleavepermonth;
											}else{
												$start = strtotime($startdate1);
												$end = strtotime($enddate);
												$days_between = (abs($end - $start) / 86400);
												$leavealotted1=round(($days_between * $leavealotted)/365);
												$totalcreditleave=$leavealotted1;
											}
										}
										if(strtotime($doj) >= strtotime($startdate)){
											if($row->Caping==1){
												$start=date('Y' ,strtotime($doj));
												$end = date('Y' , strtotime($enddate));
												$Y1=date('m',strtotime($doj));
												$M1=date('m',strtotime($enddate));
												$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
												$leavealotted1=($diff1+1) * $leavealotted;
												
												$countmonth=date('Y' ,strtotime($doj));
												$currentdate = date('Y' , strtotime(date("Y-m-d")));
												$Y=date('m',strtotime($doj));
												$M=date('m',strtotime(date("Y-m-d")));
												$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
												$totalcreditleave=($diff+1) * $creditleavepermonth;
											}else{
												$start = strtotime($doj);
												$end = strtotime($enddate);
												$days_between = (abs($end - $start) / 86400);
												$leavealotted1=round(($days_between * $leavealotted)/365);
												
												$totalcreditleave=$leavealotted1;
											}
										}
									}else{
										if($row->Caping==1){
											$start=date('Y' ,strtotime($date1));
											$end = date('Y' , strtotime($enddate));
											$Y1=date('m',strtotime($date1));
											$M1=date('m',strtotime($enddate));
											$diff1 = (($end - $start) * 12) + ($M1 - $Y1);
											$leavealotted1=($diff1+1) * $leavealotted;
											
											$countmonth=date('Y' ,strtotime($date1));
											$currentdate = date('Y' , strtotime(date("Y-m-d")));
											$Y=date('m',strtotime($date1));
											$M=date('m',strtotime(date("Y-m-d")));
											$diff = (($currentdate - $countmonth) * 12) + ($M - $Y);
											$totalcreditleave=($diff+1) * $creditleavepermonth;
										}else{	$start = strtotime($doj);
											$end = strtotime($enddate);
											$days_between = (abs($end - $start) / 86400);
											$leavealotted1=round(($days_between * $leavealotted)/365);
											
											$totalcreditleave=$leavealotted1;
										}
									}
								}
							}
							
							if($row->carriedforward==1){
								$sql1 = "SELECT * FROM EmployeeCarriedForward WHERE OrganizationId = ? and EmployeeId = ?  and FiscalId = ? and LeaveTypeId=?";
								$query1 = $this->db->prepare($sql1);
								$query1->execute(array($orgid,$empid,$preyearfiscalid,$row->Id));
								$count1 =  $query1->rowCount();
								$row1 = $query1->fetch();
								
								if($count1>=1){
									$preCFleave=$row1->CFLeave;	
								}
							}
							
							$employeeusedleave=0;
							$sql2 = "Select * from EmployeeLeaveChild as empchild,EmployeeLeave as empleave where empchild.EmployeeLeaveId=empleave.Id and empleave.OrganizationId = ? and empleave.EmployeeId =? and empleave.LeaveTypeId=? and empleave.FiscalId in(1,$fiscalid) and empchild.LossOfPay=0 and empleave.LeaveStatus=2 and empchild.Entitled=1";
							$query2 = $this->db->prepare($sql2);
							//$query2->execute(array());
							$query2->execute(array($orgid,$empid,$row->Id));
							$count2 =  $query2->rowCount();
							
							//$leavetotalEn = $row2->totalentitled;
							while($row2 = $query2->fetch()){
								if($row2->HalfDaySts == 1){
									$employeeusedleave = $employeeusedleave + 0.5;
								}else{
									$employeeusedleave++;
								}
							}
						
							$cfrleave=0;
							$sql3 = "Select * from EmployeeLeaveChild as empchild,EmployeeLeave as empleave where empchild.EmployeeLeaveId=empleave.Id and empchild.LossOfPay=0 and empchild.LeaveStatus=2  and empleave.OrganizationId = ? and empleave.EmployeeId =?  and empleave.LeaveTypeId=? and empleave.LeaveStatus=2 and empchild.CarriedForward=1 and empleave.FiscalId=?";
							$query3 = $this->db->prepare($sql3);
							$query3->execute(array($orgid,$empid,$row->Id,$fiscalid));
							while($row3 = $query3->fetch()){
								if($row3->HalfDaySts == 1){
									$cfrleave = $cfrleave + 0.5;
								}else{
									$cfrleave++;
								}
							}
						
							/* $leftalloted=$totalcreditleave-$totalutilizedleave;
							
							if($leftalloted>0)
								$balanceleave=(($preCFleave-$cfrleave) + $leftalloted); */
							
							$res = array();
							$res['id'] = (int)$row->Id;
							$res['name'] = ucwords($row->Name);
							$res['carryforward'] = $preCFleave; 
							$res['days'] = (int)$leavealotted1;//////////Alloted leave days for a year///////////////
							$res['usedleave'] = $totalutilizedleave;
							$res['leftleave'] = ($preCFleave - $cfrleave) + ($totalcreditleave - $employeeusedleave);///THIS YEAR BALANCE///
							$res['totalleave'] = $preCFleave + $leavealotted1 ; ////////////TOTAL LEAVE BALANCE FOR THIS YEAR//////////////
							$res['compoffsts'] = (int) $row->compoffsts;
							$res['workfromhomests'] =  (int)$row->workfromhomests;
							$data[] = $res;
						}
					}else{
						$sql1 = "SELECT COUNT(Id) as id FROM CompensatoryLeaves WHERE EmployeeId=$empid and LeaveId=0 and Compoffsts in (0, 1, 5) and (Expirydate >= CURDATE() or Expirydate=0000-00-00)";
						$query1 = $this->db->prepare($sql1);
						$query1->execute();
						$row1 = $query1->fetch();
						$res = array();
						$res['id'] = (int)$row->Id;
						$res['name'] = ucwords($row->Name);
						$res['carryforward'] = 0;
						$res['days'] = $row1->id;//////////Alloted leave days for a year///////////////
						$res['usedleave'] = 0;
						$res['leftleave'] = $row1->id;/////THIS YEAR BALANCE/////
						$res['totalleave'] =  $row1->id;////////////TOTAL LEAVE BALANCE FOR THIS YEAR//////////////
						$res['compoffsts'] = (int) $row->compoffsts;
						$res['workfromhomests'] =  (int)$row->workfromhomests;
						$data[] = $res;
					}
				}	
			}else{
				$status=true;
				$successMsg = LEAVETYPE_MODULE_GETALL;
			}
		}catch(Exception $e) {
			$status=false;
			$errorMsg = 'Message: ' .$e->getMessage();
		}
		echo json_encode($data);
    }
	
	public function getAllHierarchyEmployee($request,$val=0)
    {
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		$mid   = strtolower($request[0]);	//USER ID CONTAINS IN ARRAY FIRST VALUE;
		$orgid = $request[1];	//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$sql = "";
		$sts=0;	$hrsts=0;
		
		if( $hrsts==1){
			$sql = "SELECT * FROM EmployeeMaster WHERE OrganizationId = :id and (DOL='0000-00-00' or DOL>curdate()) and Is_Delete=0 order by FirstName ";
		}else{		
			$ids =Utils::getReportingIds($mid, $this->db,$orgid);
			$sql = "SELECT * FROM EmployeeMaster WHERE OrganizationId = :id and (DOL='0000-00-00' or DOL>curdate()) and Is_Delete=0 and Id in ($ids) order by FirstName ";
		}
		
		if($val)
			$sql = "SELECT * FROM EmployeeMaster WHERE OrganizationId = :id and (DOL='0000-00-00' or DOL>curdate()) and Is_Delete=0 order by FirstName "; 
			$query = $this->db->prepare($sql);
			try{
				$query->execute(array(':id' => $orgid ));
				$count =  $query->rowCount();
			}catch(Exception $e) {
				$errorMsg = 'Message: ' .$e->getMessage();
			}
			
		if($count>=1){
			$status=true;
			$successMsg=$count." record found";
			while($row = $query->fetch()){
				$res = array();
				$res['id'] = (int)$row->Id;
				$res['idst'] = $row->Id; //////////////use it when use in multiselect/bootstrap select
				$res['doj'] = $row->DOJ;
				$res['name'] = $row->EmployeeCode." - ". ucwords(strtolower($row->FirstName." ".$row->LastName));
				$res['sts'] = 0;
				$res['empreport'] = Utils::getName($row->ReportingTo,"EmployeeMaster", "FirstName", $this->db).' '.Utils::getName($row->ReportingTo,"EmployeeMaster", "LastName", $this->db);
				$res['empdivision'] = Utils::getName($row->Division,"DivisionMaster", "Name", $this->db);
				$res['empdepartId'] = $row->Department;
				$res['empdepartment'] = Utils::getName($row->Department,"DepartmentMaster", "Name", $this->db);
				$res['empdesignation'] = Utils::getName($row->Designation,"DesignationMaster", "Name", $this->db);
				$res['empdesignationid'] = $row->Designation;
				$res['empchannel'] = Utils::getName($row->Channel,"ChannelMaster", "Name", $this->db);
				$res['empgrade'] = Utils::getName($row->Grade,"GradeMaster", "Name", $this->db);
				$res['empsts'] = Utils::getOtherName($row->EmployeeStatus,"EmployeeStatus", $this->db);
				$res['empsts1'] = $row->EmployeeStatus;
				$res['salaryctc'] = $row->EmployeeCTC;
				$res['emplocation'] = Utils::getName($row->Location,"LocationMaster", "Name", $this->db);
				$res['empcurency'] =  Utils::getDivisioncurrency($row->Division,$this->db);
				$res['empcurrentemail'] = Utils::decode5t($row->CurrentEmailId);
				$res['emphomeemail'] = Utils::decode5t($row->HomeEmailId);
				$res['empcompanyemail'] = Utils::decode5t($row->CompanyEmail);

				$desigid = $row->Designation;
				$data[] = $res;
			}
        }else{
			$status=true;
			$successMsg = EMPLOYEE_MODULE_GETALL;
		}
		echo json_encode($data);
    }
	
	public function register_org()
	{   
		$mdate=date('Y-m-d');
		$count=0;$count1=0;$trialorgid="";
		$org_name= isset($_REQUEST['org_name']) ? $_REQUEST['org_name'] : "";
        $contact_person_name = isset($_REQUEST['name']) ? $_REQUEST['name'] : "";
        $email = isset($_REQUEST['email']) ? strtolower(trim($_REQUEST['email'])) : "";
        $password = isset($_REQUEST['password']) ? $_REQUEST['password'] : "123456";
        $countrycode = isset($_REQUEST['countrycode']) ? $_REQUEST['countrycode'] : "";
        $phone   = isset($_REQUEST['phone']) ? $_REQUEST['phone'] : "";
        $county  = isset($_REQUEST['country']) ? $_REQUEST['country'] : "0";
        $address = isset($_REQUEST['address']) ? $_REQUEST['address'] : "";
		$city = isset($_REQUEST['city']) ? $_REQUEST['city'] : "";
		$countryname=$this->getcountryName($county);
	
		$sql="Select Id, EmployeeId from UserMaster where Username=?  ";
		$query = $this->db->prepare($sql);               
		$query->execute(array( Utils::encode5t($email)));
		$count1 =  $query->rowCount();
		if($count1>0){
			return "2";
		}
		
		$sql2="Select Id, EmployeeId from UserMaster where  username_mobile=? ";
		$query2 = $this->db->prepare($sql2);               
		$query2->execute(array( Utils::encode5t($phone)));
		$count2 =  $query2->rowCount();
		if($count2>0){
			return "3";
		}else{
			$sql4 ="SELECT * FROM `TrialOrganization` where Email=? or PhoneNumber=?";
			$query4 = $this->db->prepare($sql4);               
			$query4->execute(array($email, $phone));
			if($query4->rowCount()==0){
				$sql1="INSERT INTO `TrialOrganization`(`CompanyName`, `ContactPersonName`, `countrycode`, `PhoneNumber`, `Email`, `Address`, `Country`, `City`, `CreatedDate`, `extended_days`, `mail_varified`) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
				$query1 = $this->db->prepare($sql1);               
				$query1->execute(array($org_name, $contact_person_name, $countrycode, $phone, $email, $address, $countryname, $city, $mdate, 0, 0));
				$count =  $query1->rowCount();
				$trialorgid=$this->db->lastInsertId();
				if($count>0){
					Utils::Trace("register org for app ------>".$org_name);
					$data                = array();
					$res = array();	
					$data['f_name']      = ucwords($contact_person_name);
					$enc_email= Utils::encode5t($email);
					$msg ='
					<html>
					 <head>
					  <meta http-equiv=Content-Type content="text/html; charset=windows-1252">
					  <meta name=Generator content="Microsoft Word 12 (filtered)">
					  <style>
					  </style>
					 </head>

					 <body lang=EN-US link=blue vlink=purple>

						<div class=Section1>
							<div >
								<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="550" style="550px;border-collapse:collapse" >
									<tr style="height:328.85pt">
										<td width=917 valign=top style="width:687.75px;padding:0in 0in 0in 0in; height:328.85px">
											<div align=center>
												<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="100%" style="width:100.0%; border-collapse:collapse">
													<tr>
														<td valign=top style="background:#ffffff;padding:0in 16.1pt 0in 16.1pt">
															<div align=center>
																<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="100%" style="width:100.0%;border-collapse:collapse">
																	<tr>
																		<td valign=top style="padding:21.5pt 0in 5.5pt 0in">
																			<p class=MsoNormal align=center style="margin-bottom:0in;margin-bottom: .0001pt;text-align:center;line-height:normal"><span style="font-size:12.0pt;font-family:Arial,sans-serif"><img width=200 id="Picture 1" src="https://ubitech.ubihrm.com/public/avatars/ubihrm.png" alt="ubitech solutions"></span></p>
																		</td>
																	</tr>
																</table>
															</div>
														</td>
													</tr>
													
													<tr>
														<td valign=top style="background:#ffffff;padding:0in 16.1pt 0in 16.1pt">
															<div align=center>
																<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="100%" style="width:100.0%; border-collapse:collapse">
																	<tr>
																		<td valign=top style="padding:0in 0in 0in 0in">
																			<div align=center>
																				<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="100%" style="width:100.0%; border-collapse:collapse">
																					<tr>
																						<td valign=top style="padding:0in 0in 0in 0in">
																							<div align=center>
																								<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="100%" style="width:100.0%;background:white">
																									<tr>
																										<td width="550" valign=top style="width:550px;">
																											<p class=MsoNormal align=center style="text-align: center;line-height: normal"><span style="font-size:12.0pt;font-family:Arial,sans-serif">&nbsp;</span></p>
																										</td>
																									</tr>
																								</table>
																							</div>
																						</td>
																					</tr>
																				</table>
																			</div>
																		</td>
																	</tr>
																</table>
															</div>
														</td>
													</tr>
							
													<tr>
														<td valign=top style="padding:0in 0in 0in 0in">
															<div align=center>
																<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="550" style="width:550px;   border-collapse:collapse">
																	<tr>
																		<td valign=top style="padding:0in 0in 0in 0in">
																			<div align=center>
																				<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="550" style="width:550px;border-collapse:collapse">
																					<tr>
																						<td width=30 valign=top style="width:22.5pt;padding:0in 0in 0in 0in">
																							<p class=MsoNormal align=right style="margin-bottom:0in;margin-bottom:.0001pt; text-align:right; line-height:normal"><span style="font-size:12.0pt; font-family:Arial,sans-serif"></span></p>
																						</td>
																						<td width="550" valign=top style="width:550px;padding:0in 37.6pt 0in 21.5pt">
																							<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 align=left width="550" style="550px; border-collapse:collapse">
																								<tr>
																									<td valign=top style="padding:0in 0in 21.5pt 0in">
																										<p class=MsoNormal align=center style="margin-bottom: 0in; margin-bottom:.0001pt;text-align:left;line-height:22.55pt"><b><p style="font-size: 20.0pt;font-family:Helvetica,sans-serif;color:#606060;text-align:center; margin-top: 1px; margin-bottom: 1px;">Welcome - now let&#39;s get started!
																										</p>  	
																										<div align=center>
																											<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="550" style="width: 550px; border-collapse:collapse">
																											<tr>
																											<td align="center" style="padding:0in 0in 0in 0in">
																											<a>
																											<p class=MsoNormal align=center style="margin-bottom:0in;								margin-bottom:.0001pt;text-align:center;line-height:normal; background:white;width: 250px;padding: 2px;font-size:16px;"></span></b></span></p></a>
																											</td>
																											</tr>
																											</table>
																										</div>
																										<span style="font-family:Helvetica,sans-serif; text-align: left"><br/>Dear '.$contact_person_name.', </span></b>
																										<p style="text-align: left;" class="paragraph-text">Greetings from ubiHRM Team! </p>
																										<p style="text-align: left;"class="paragraph-text">
																										You are registered successfully with Admin profile on ubiHRM App for "'.ucwords($org_name).'". Our Sales team will assist you in a short while.  <br/><br/>In the meanwhile <a href="https://ubitech.ubihrm.com/ubiapp/survey?city='.$city.'&country='.$countryname.'&phone='.$phone.'&org_name='.$org_name.'&email='.$enc_email.'&first_name='.$contact_person_name.'&orgid='.$trialorgid.'">Please take a short survey</a> to help us understand your requirements.<!-- <a href="mailto:ubihrmsupport@ubitechsolutions.com">Contact us</a> or <a target="_blank" href="https://www.youtube.com/channel/UCLplA-GWJOKZTwGlAaVKezg">View our Channel </a>and learn about key features and best practices.-->
																										</p>
																										</p>
																									</td>
																								</tr>
																								<tr>
																								</tr>
																								<tr>
																								<td valign=top style="padding:0in 0in 2.7pt 0in">Cheers,<br/>Team ubiHRM<br/>Tel/ Whatsapp(India): +91 7773000234<br/>Tel/ Whatsapp(Overseas): +971 55-5524131<br/>Email: ubihrmsupport@ubitechsolutions.com
																								</td>
																								</tr>
																							</table>
																						</td>
																					</tr>
																				</table>
																			</div>
																		</td>
																	</tr>
																</table>
															</div>
														</td>
													</tr>
												</table>
											</div>
										</td>
									</tr>
								</table>
							</div>
						</div>
					</body>
					</html>';
					$subject = "Ubihrm Registration Successful";
					$sts=Utils::sendMail($email,'UBIHRM',$subject,$msg);
					Utils::Trace($msg);			
								
					$msg1='<html>
					 <head>
					  <meta http-equiv=Content-Type content="text/html; charset=windows-1252">
					  <meta name=Generator content="Microsoft Word 12 (filtered)">
					  <style>
					  </style>
					 </head>

					 <body lang=EN-US link=blue vlink=purple>

						<div class=Section1>
							<div >
								<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="550" style="550px;border-collapse:collapse" >
									<tr style="height:328.85pt">
										<td width=917 valign=top style="width:687.75px;padding:0in 0in 0in 0in; height:328.85px">
											<div align=center>
												<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="100%" style="width:100.0%; border-collapse:collapse">
													<tr>
														<td valign=top style="background:#ffffff;padding:0in 16.1pt 0in 16.1pt">
															<div align=center>
																<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="100%" style="width:100.0%;border-collapse:collapse">
																	<tr>
																		<td valign=top style="padding:21.5pt 0in 5.5pt 0in">
																			<p class=MsoNormal align=center style="margin-bottom:0in;margin-bottom: .0001pt;text-align:center;line-height:normal"><span style="font-size:12.0pt;font-family:Arial,sans-serif"><img width=200 id="Picture 1" src="https://ubitech.ubihrm.com/public/avatars/ubihrm.png" alt="ubitech solutions"></span></p>
																		</td>
																	</tr>
																</table>
															</div>
														</td>
													</tr>
													
													<tr>
														<td valign=top style="background:#ffffff;padding:0in 16.1pt 0in 16.1pt">
															<div align=center>
																<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="100%" style="width:100.0%; border-collapse:collapse">
																	<tr>
																		<td valign=top style="padding:0in 0in 0in 0in">
																			<div align=center>
																				<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="100%" style="width:100.0%; border-collapse:collapse">
																					<tr>
																						<td valign=top style="padding:0in 0in 0in 0in">
																							<div align=center>
																								<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="100%" style="width:100.0%;background:white">
																									<tr>
																										<td width="550" valign=top style="width:550px;">
																											<p class=MsoNormal align=center style="text-align: center;line-height: normal"><span style="font-size:12.0pt;font-family:Arial,sans-serif">&nbsp;</span></p>
																										</td>
																									</tr>
																								</table>
																							</div>
																						</td>
																					</tr>
																				</table>
																			</div>
																		</td>
																	</tr>
																</table>
															</div>
														</td>
													</tr>
							
													<tr>
														<td valign=top style="padding:0in 0in 0in 0in">
															<div align=center>
																<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="550" style="width:550px;   border-collapse:collapse">
																	<tr>
																		<td valign=top style="padding:0in 0in 0in 0in">
																			<div align=center>
																				<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="550" style="width:550px;border-collapse:collapse">
																					<tr>
																						<td width=30 valign=top style="width:22.5pt;padding:0in 0in 0in 0in">
																							<p class=MsoNormal align=right style="margin-bottom:0in;margin-bottom:.0001pt; text-align:right; line-height:normal"><span style="font-size:12.0pt; font-family:Arial,sans-serif"></span></p>
																						</td>
																						<td width="550" valign=top style="width:550px;padding:0in 37.6pt 0in 21.5pt">
																							<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 align=left width="550" style="550px; border-collapse:collapse">
																								<tr>
																									<td valign=top style="padding:0in 0in 21.5pt 0in">
																										<p class=MsoNormal align=center style="margin-bottom: 0in; margin-bottom:.0001pt;text-align:left;line-height:22.55pt"><b><p style="font-size: 20.0pt;font-family:Helvetica,sans-serif;color:#606060;text-align:center; margin-top: 1px; margin-bottom: 1px;">New registration Successful in UBIHRM
																										</p>  	
																										<div align=center>
																											<table class=MsoNormalTable border=0 cellspacing=0 cellpadding=0 width="550" style="width: 550px; border-collapse:collapse">
																											<tr>
																											<td align="center" style="padding:0in 0in 0in 0in">
																											<a>
																											<p class=MsoNormal align=center style="margin-bottom:0in;								margin-bottom:.0001pt;text-align:center;line-height:normal; background:white;width: 250px;padding: 2px;font-size:16px;"></span></b></span></p></a>
																											</td>
																											</tr>
																											</table>
																										</div>
																										<div>
																										<p style="font-family:Helvetica,sans-serif; font-size:10.0pt;text-align: left; margin-top: 30px" class="paragraph-text">
																										Company Name: '.$org_name.'</p>
																										<p style="font-family:Helvetica,sans-serif; font-size:10.0pt;text-align: left;" class="paragraph-text">
																										Contact Person Name: '.$contact_person_name.'</p>
																										<p style="font-family:Helvetica,sans-serif; font-size:10.0pt;text-align: left;" class="paragraph-text">
																										Registered Email ID: '.$email.'</p>
																										<p style="font-family:Helvetica,sans-serif; font-size:10.0pt;text-align: left;" class="paragraph-text">
																										Country: '.$countryname.'</p>
																										<p style="font-family:Helvetica,sans-serif; font-size:10.0pt;text-align: left;" class="paragraph-text">
																										City: '.$city.'</p>
																										<p style="font-family:Helvetica,sans-serif; font-size:10.0pt;text-align: left;" class="paragraph-text">
																										Contact No.: '.$phone.'</p>
																										</div>
																								
																										</p> 
																										</p>
																									</td>
																								</tr>
																								<tr>
																								</tr>
																								<tr>
																								<!-- <td valign=top style="padding:0in 0in 2.7pt 0in">Cheers,<br/>Team ubiHRM<br/>Tel/ Whatsapp(India): +91 7773000234<br/>Tel/ Whatsapp(Overseas): +971 55-5524131<br/>Email: ubihrmsupport@ubitechsolutions.com
																								</td> -->
																								</tr>
																							</table>
																						</td>
																					</tr>
																				</table>
																			</div>
																		</td>
																	</tr>
																</table>
															</div>
														</td>
													</tr>
												</table>
											</div>
										</td>
									</tr>
								</table>
							</div>
						</div>
					</body>
					</html>';
					Utils::Trace("register org--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- ".$msg1);		
					$subject = "New registration Successful in UBIHRM";
					$sts1=Utils::sendMail("ubihrmsupport@ubitechsolutions.com",'UBIHRM',$subject,$msg1);
					$sts2=Utils::sendMail("sales@ubitechsolutions.com",'UBIHRM',$subject,$msg1);
					$sts3=Utils::sendMail("anita@ubitechsolutions.com",'UBIHRM',$subject,$msg1);
				
					$data['id'] = 1;
					$data['sts'] = "true";
					$res[]=$data;
					Utils::Trace("register org for app ------>".$phone);
					Utils::Trace("register org for app ------>".$msg);
					
					return "1";
				}
			}else{
				return "4";
			}
		}
		 return "0";
	 }
	 
	public function getTimeoffapproval($arr){
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array(); 
		$userid  = $arr[0];
        $orgid=$arr[1];		//USER ID CONTAINS IN ARRAY FIRST VALUE;
		$datafor = $arr[2];
		$stsn=$this->getstsid($arr[2],'LeaveStatus');
		//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$startdate = date("Y-m-1");
		$enddate = date("Y-m-t");
		$sql2= "SELECT * FROM  `FiscalMaster` WHERE  `OrganizationId` =$orgid AND  `FiscalSts` =1";
		$query2      = $this->db->prepare($sql2);
		$query2->execute();
		$count = $query2->rowCount();
		if($count==1){
			if($row2 = $query2->fetch()){
				$startdate = $row2->StartDate;
				$enddate = $row2->EndDate;
			}
		}
		
		$sts=1;
		$hrsts=0;
		//$ids = Utils::getReportingIds($userid, $this->db);
		$sWhere = "";
		/* if($hrsts==1){
			$sWhere = " WHERE ApprovalSts=$stsn and  OrganizationId= $orgid and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0)";
		} else{*/
			
		$sWhere = " WHERE ApprovalSts=$stsn and Id IN (SELECT TimeofId FROM TimeoffApproval Where ApproverId=$userid ) and OrganizationId= $orgid and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0) and (TimeofDate between '$startdate' and '$enddate')";
			
		//}
		
		$present =0;$absent =0;$leave =0;$total =0;$month=0;
		$sql1 = "SELECT * FROM   Timeoff $sWhere ORDER BY  CreatedDate desc";
		$query1 = $this->db->prepare($sql1);
		$query1->execute();
		$total = $query1->rowCount();
		
		if($total > 0){
			while($row1 = $query1->fetch())
			{
				$sts=$this->getTimeoffApproverSts($row1->Id,$userid);
				$res = array();
				//$res['id'] = $row1->Id;
				//$res['id'] = Utils::getName($row1->EmployeeIds,'EmployeeMaster','FirstName',$this->db);
				$res['total'] = $total;
				$res['Id'] = $row1->Id;
				$res['name'] = $this->getName($row1->EmployeeId);
				$lsts = $row1->ApprovalSts;
				if($lsts==3)
				{
					$res['LeaveStatus']='Pending';
				}
				if($lsts==2){
					$res['LeaveStatus']='Approved';
				}
				if($lsts==1){
					$res['LeaveStatus']='Rejected';
				}
               $res['FDate'] = date("g:i a", strtotime($row1->TimeFrom));
			   $TDate = date("g:i a", strtotime($row1->TimeTo));
			   if($res['FDate']==$TDate){
				    $res['TDate']="";
			   }
			   else{
				   $res['TDate'] =" to ".$TDate; 
			   }
				 $res['ApplyDate'] = date("d-M-Y", strtotime($row1->CreatedDate));
				 $res['LeaveReason'] = $row1->Reason;
				 $res['Pstatus']  = $this->gettimeoffpendingatstatus($lsts, $row1->Id);
				$Pstatus=$res['Pstatus'];
				if($Pstatus!=$userid && $Pstatus!=0 ){
					$name=$this->getName($Pstatus);
					$res['Pstatus']="Pending at $name";
				}
				else{
					$res['Pstatus']="";
				}
	
				$sq = "SELECT HRSts  FROM UserMaster WHERE EmployeeId = ? and OrganizationId = ? ";
			
				$query = $this->db->prepare($sq);
				try{
					$query->execute(array($userid, $orgid ));
					while($row = $query->fetch())
					{
						$res['HRSts'] = $row->HRSts;
					}
				}catch(Exception $e) {
					
				}
				$data[] = $res;
			}
		}
		return $data ;
		//return $data[1]["LeaveStatus"] ;
	}
	
	//////////////////////////// to find out the approver on which the timeoff is pending ///////////////////////////
	
	public function gettimeoffApproverPendingSts($id,$sts)
	{
		$name ="0";
		if($sts==2)//approved
			$sql = "SELECT * FROM TimeoffApproval where TimeofId=? and ApproverSts=? order by Id desc limit 1";
		else//pending	
			$sql = "SELECT * FROM TimeoffApproval where TimeofId=? and ApproverSts=? order by Id asc limit 1";
		$query = $this->db->prepare($sql);
		try{
			$query->execute(array( $id,$sts ));
			while($row = $query->fetch())
			{
				$name = $row->ApproverId;
			}
		}catch(Exception $e) {}
		return $name;
	}
		
	public function gettimeoffpendingatstatus($sts,$timeoffid){
		if($sts==3){
			$pendingapprover=$this->gettimeoffApproverPendingSts($timeoffid,3);
			return	$pendingapprover;
		}else{
			return $this->getleavetype($sts);
		}
	}

	public function getTimeoffApproverSts($id,$userid)
	{
		$flg =false;
		$employee=0;
		$sql = "SELECT * FROM TimeoffApproval WHERE TimeofId = ? ";
        $query = $this->db->prepare($sql);
		try{
			$query->execute(array( $id ));
			while($row = $query->fetch())
			{
				if($row->ApproverSts==3){
					$employee=$row->ApproverId;
					break;
				}
			}
			if($employee ==  $userid){
					$flg = true;
			}
		}catch(Exception $e) {
			
		}
		return $flg;
	}
	
	
	public function ApproveTimeoff($request)
    {
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		$mid   = $request[0];	//USER ID CONTAINS IN ARRAY FIRST VALUE;
		$orgid = $request[1];	//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$timeoffid = $request[2];
		$sts = $request[3];
		$mdate = date("Y-m-d H:i:s");
		
		if($sts==2){$approver_val='approved';}
		else{$approver_val='rejected';}
		
		$sql = "UPDATE TimeoffApproval SET ApproverSts =$request[3], ApprovalDate ='$mdate', ApproverComment='$request[4]' WHERE TimeofId =$timeoffid AND ApproverId=$mid  and OrganizationId=$orgid and  ApproverSts = 3 and ApprovalDate='0000-00-00 00:00:00'";
		//Utils::Trace($sql);
		try{
			$query = $this->db->prepare($sql);
			$query->execute(array());
			$count =  $query->rowCount();		
			if ($count >= 1) {
				$empid=Utils::getName($timeoffid,'Timeoff','EmployeeId',$this->db);
                $empname=ucwords(strtolower(Utils::getEmployeeName($empid,$this->db)));
				$approvername=ucwords(strtolower(Utils::getEmployeeName($mid,$this->db)));
				$applydate=Utils::getName($timeoffid,'Timeoff','CreatedDate',$this->db);
				$timeoffdate=Utils::getName($timeoffid,'Timeoff','TimeofDate',$this->db);
				$from=date('h:i A', strtotime(Utils::getName($timeoffid,'Timeoff','TimeFrom',$this->db)));
				$to=date('h:i A', strtotime(Utils::getName($timeoffid,'Timeoff','TimeTo',$this->db)));
				$msg="<b>$empname</b> timeoff <b>$approver_val</b> by <b>$approvername</b> | Applied On: <b>$applydate</b> | Timeoff Date: <b>$timeoffdate</b> | From: <b>$from</b> | To: <b>$to</b>";
				$sql = "INSERT INTO ActivityHistoryMaster ( LastModifiedById, Module, ActionPerformed,  OrganizationId) VALUES (?, ?, ?, ?)";
				$query = $this->db->prepare($sql);
				$query->execute(array($mid, "UBIHRM APP", $msg, $orgid));
				
				$status =true;
				$successMsg="Time off has been approved";
				$empname="";
				$userid="";
				$empmail="";
				$timeoffreason="";
				$timeoffdate="";
				$fromtime="";
				$totime="";
				
				$sql3="select * from Timeoff where Id=$timeoffid";
				//Utils::Trace($sql3." ".$leaveid);
				$query3=$this->db->prepare($sql3);
				$query3->execute();
				if($row3=$query3->fetch()){
					$userid=$row3->EmployeeId;
					$timeoffreason=$row3->Reason;
					$timeoffdate=Utils::dateformatter($row3->TimeofDate);
					$fromtime=date('h:i A',strtotime($row3->TimeFrom));
					$totime=date('h:i A',strtotime($row3->TimeTo));
					$appliedon=Utils::dateformatter($row3->CreatedDate);
					//Utils::Trace("name and email".$emp_mail." ".$emp_name);
				}
					$empname=ucwords(Utils::getName($userid,'EmployeeMaster','FirstName',$this->db));
					$empemail=Utils::decode5t(Utils::getName($userid,'EmployeeMaster','CompanyEmail',$this->db));
					///////// fetching timeoff approval history ///////////
					$approverhistory="";
					$sql = "SELECT * FROM TimeoffApproval WHERE OrganizationId = ? AND TimeofId = ? AND ApproverSts<>3 ";
					$query = $this->db->prepare($sql);
					$query->execute(array($orgid, $timeoffid));
					$count =  $query->rowCount();
					if($count>=1){
						$approverhistory="<p><b>Approval History</b></p>
						<table border='1' style=' border-collapse: collapse;width:70%'>
						<tr style=' background-color: rgba(107, 58, 137, 0.91);color: rgba(255, 247, 247, 1);'>
											
											<th>Approval Status</th>
											<th>Approver</th>
											<th>Approval Date</th>
											<th>Remarks</th>
						</tr>";
					}
					while($r=$query->fetch()){
						$approvername=ucwords(Utils::getEmployeeName($r->ApproverId,$this->db));
						$approvalsts=$r->ApproverSts;
						if($approvalsts==1){
							$approvalsts="Rejected";
						}elseif($approvalsts==2){
							$approvalsts="Approved";
						}elseif($approvalsts==3){
							$approvalsts="Pending";
						}elseif($approvalsts==4){
							$approvalsts="Cancel";
						}elseif($approvalsts==5){
							$approvalsts="Withdrawn";
						}elseif($approvalsts==7){
							$approvalsts="Escalated";
						}
						$approvaldate="";
						$approvaldate=Utils::datetimeformatter($r->ApprovalDate);
						$approvercomment=$r->ApproverComment;
						$approverhistory.="<tr>
														
														<th>$approvalsts</th>
														<th>$approvername</th>
														<th>$approvaldate</th>
														<th>$approvercomment</th>
														</tr>";
					}
					if($count>=1){
						$approverhistory.="</table>";
					}
			   if($request[3]==2){
					$sql1 = "select * from TimeoffApproval WHERE TimeofId = ? and ApproverSts=3 and OrganizationId=?";
					$query1 = $this->db->prepare($sql1);
					$query1->execute(array( $timeoffid, $orgid));
					if($r=$query1->fetch())
					{				
						$nxtapproverid=$r->ApproverId;
						$approvelink=URL."approvalbymail/viewapprovetimeoffapproval/$nxtapproverid/$orgid/$timeoffid/2";
						$rejectlink=URL."approvalbymail/viewapprovetimeoffapproval/$nxtapproverid/$orgid/$timeoffid/1";
						$seniorname=Utils::getName($nxtapproverid,'EmployeeMaster','FirstName',$this->db);
						$senioremail=Utils::decode5t(Utils::getName($nxtapproverid,'EmployeeMaster','CompanyEmail',$this->db));
						$title="Timeoff approval";
						$msg="<table>
											<tr><td>Requested by: $empname</td></tr>
											<tr><td>Reason for leave: $timeoffreason</td></tr>
											<tr><td>Date: $timeoffdate</td></tr>
											<tr><td>Duration: from $fromtime to $totime</td></tr>
											<tr><td>Applied on: $appliedon</td></tr>
											</table><br>
											$approverhistory<br>
											<table>
											<tr><td><br/><br/>
													<a href='$approvelink'   style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: green;   -webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px green; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Approve</a>
													&nbsp;&nbsp;
													&nbsp;&nbsp;
													<a href='$rejectlink'  style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: brown; 	-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px brown; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Reject</a>
													<br/><br/>
													</td>															
													</tr>	
							</table>";
						Utils::Trace($senioremail." ".$msg);
						Utils::sendMail($senioremail,$empname,$title,$msg);
					}
					if($query1->rowCount()==0){
						
						$sql2 = "UPDATE Timeoff SET ApprovalSts =?, ModifiedDate=?,ApproverId=?,ApproverComment=? WHERE Id =? ";
						$query2 = $this->db->prepare($sql2);
						$query2->execute(array(2,$mdate,$mid, $request[4],$timeoffid));
						if ($count >= 1) {
							$successMsg = "Time off application is approved successfully";
							
							/*generate mail and alert for time off approved*/
							Alerts::generateActionAlerts(53,$timeoffid,$orgid,$this->db);
							
							   $empname="";
								$userid="";
								$empmail="";
								$timeoffreason="";
								$timeoffdate="";
								$fromtime="";
								$totime="";
								
								$sql3="select * from Timeoff where Id=$timeoffid";
								//Utils::Trace($sql3." ".$leaveid);
								$query3=$this->db->prepare($sql3);
								$query3->execute();
								if($row3=$query3->fetch()){
									$userid=$row3->EmployeeId;
									$timeoffreason=$row3->Reason;
									$timeoffdate=Utils::dateformatter($row3->TimeofDate);
									$fromtime=$row3->TimeFrom;
									$totime=$row3->TimeTo;
									//Utils::Trace("name and email".$emp_mail." ".$emp_name);
								}
							//$empname=ucwords(Utils::getName($userid,'EmployeeMaster','FirstName',$this->db));
							$empname=ucwords(Utils::getEmployeeName($userid,$this->db));
							$approverhistory="";
						$sql = "SELECT * FROM TimeoffApproval WHERE OrganizationId = ? AND TimeofId = ? AND ApproverSts<>3 ";
						$query = $this->db->prepare($sql);
						$query->execute(array($orgid, $timeoffid));
						$count =  $query->rowCount();
						if($count>=1){
							$approverhistory="<p><b>Approval History</b></p>
							<table border='1' style=' border-collapse: collapse;width:70%'>
							<tr style=' background-color: rgba(107, 58, 137, 0.91);color: rgba(255, 247, 247, 1);'>
											
												<th>Approval Status</th>
													<th>Approver</th>
												<th>Approval Date</th>
												<th>Remarks</th>
							</tr>";
						}
						while($r=$query->fetch()){
							$approvername=ucwords(Utils::getEmployeeName($r->ApproverId,$this->db));
							$approvalsts=$r->ApproverSts;
							if($approvalsts==1){
								$approvalsts="Rejected";
							}elseif($approvalsts==2){
								$approvalsts="Approved";
							}elseif($approvalsts==3){
								$approvalsts="Pending";
							}elseif($approvalsts==4){
								$approvalsts="Cancel";
							}elseif($approvalsts==5){
								$approvalsts="Withdrawn";
							}elseif($approvalsts==7){
								$approvalsts="Escalated";
							}
							$approvaldate="";
							$approvaldate=Utils::datetimeformatter($r->ApprovalDate);
							$approvercomment=$r->ApproverComment;
							$approverhistory.="<tr>
															
															<th>$approvalsts</th>
															<th>$approvername</th>
															<th>$approvaldate</th>
															<th>$approvercomment</th>
															</tr>";
						}
						if($count>=1){
							$approverhistory.="</table>";
						}
							$title="Application for Time Off is accepted";
							
							
							
							$msg="Dear $empname,<br>Your application for Time off is accepted.";
							$msg.="<table>
											<tr><td>Reason for leave: $timeoffreason</td></tr>
											<tr><td>Date: $timeoffdate</td></tr>
											<tr><td>Duration: from $fromtime to $totime</td></tr>
											</table><br>
											$approverhistory<br>
											<table>";
							$sts=Utils::sendMail($empemail,$empname,$title,$msg);
							if($sts){
								Utils::Trace("Mail sent successfully for time off . Tittle =$title, Message=$msg");
							}else{
								Utils::Trace("Mail sent failed for time off .Tittle =$title, Message=$msg");
							}
						}
				   }
					
			   }else{
				   $status=true;
					$successMsg = "Time off application is rejected successfully";
					$sql1 = "UPDATE Timeoff SET ApprovalSts =?, ApproverId=?,ApproverComment=?, ModifiedDate=? WHERE Id =? ";
					$query = $this->db->prepare($sql1);
					$query->execute(array(1,$mid,$request[4],$mdate,$timeoffid));
					
					/*generate mail and alert for time off request rejected*/
						Alerts::generateActionAlerts(60,$timeoffid,$orgid,$this->db);
							$empname="";
							$userid="";
							$empmail="";
							$timeoffreason="";
							$timeoffdate="";
							$fromtime="";
							$totime="";
							
							$sql3="select * from Timeoff where Id=$timeoffid";
							//Utils::Trace($sql3." ".$leaveid);
							$query3=$this->db->prepare($sql3);
							$query3->execute();
							if($row3=$query3->fetch()){
								$userid=$row3->EmployeeId;
								$timeoffreason=$row3->Reason;
								$timeoffdate=Utils::dateformatter($row3->TimeofDate);
								$fromtime=$row3->TimeFrom;
								$totime=$row3->TimeTo;
								//Utils::Trace("name and email".$emp_mail." ".$emp_name);
							}
					$empname=ucwords(Utils::getName($userid,'EmployeeMaster','FirstName',$this->db));
					$approverhistory="";
					$sql = "SELECT * FROM TimeoffApproval WHERE OrganizationId = ? AND TimeofId = ? AND ApproverSts<>3 ";
					$query = $this->db->prepare($sql);
					$query->execute(array($orgid, $timeoffid));
					$count =  $query->rowCount();
					if($count>=1){
						$approverhistory="<p><b>Approval History</b></p>
						<table border='1' style=' border-collapse: collapse;width:70%'>
						<tr style=' background-color: rgba(107, 58, 137, 0.91);color: rgba(255, 247, 247, 1);'>
											
											<th>Approval Status</th>
											<th>Approver</th>
											<th>Approval Date</th>
											<th>Remarks</th>
						</tr>";
					}
					while($r=$query->fetch()){
						$approvername=ucwords(Utils::getEmployeeName($r->ApproverId,$this->db));
						$approvalsts=$r->ApproverSts;
						if($approvalsts==1){
							$approvalsts="Rejected";
						}elseif($approvalsts==2){
							$approvalsts="Approved";
						}elseif($approvalsts==3){
							$approvalsts="Pending";
						}elseif($approvalsts==4){
							$approvalsts="Cancel";
						}elseif($approvalsts==5){
							$approvalsts="Withdrawn";
						}elseif($approvalsts==7){
							$approvalsts="Escalated";
						}
						$approvaldate="";
						$approvaldate=Utils::datetimeformatter($r->ApprovalDate);
						$approvercomment=$r->ApproverComment;
						$approverhistory.="<tr>
														
														<th>$approvalsts</th>
														<th>$approvername</th>
														<th>$approvaldate</th>
														<th>$approvercomment</th>
														</tr>";
					}
					if($count>=1){
						$approverhistory.="</table>";
					}
						$title="Application for Timeoff is Rejected";
						
						$msg="Dear $empname,<br>
								Your Application for TimeOff is Rejected.";
						$msg.="<table>
										
										<tr><td>Reason for leave: $timeoffreason</td></tr>
										<tr><td>Date: $timeoffdate</td></tr>
										<tr><td>Duration: from $fromtime to $totime</td></tr>
										</table><br>
										$approverhistory<br>
										<table>";
				
					
								Utils::Trace($empemail." ".$msg);
								Utils::sendMail($empemail,$empname,$title,$msg);
			   }
			  
			  
			} else{
						$sql1 = "select * from TimeoffApproval WHERE TimeofId=?";
						$query1 = $this->db->prepare($sql1);
						$query1->execute(array( $timeoffid));
						$sts=0;
						if($r=$query1->fetch()){
							$sts=$r->ApproverSts;
						}
						if($sts==1){
							$sts="rejected";
						}elseif($sts==2){
							$sts="approved";
						}else{
							$sts="answered";
						}
							$status=false;
							$errorMsg="Request already been ".$sts;
				}
		}catch(Exception $e) {
				$errorMsg = 'Message: ' .$e->getMessage();
				Utils::Trace($errorMsg);
			}
		
		$result["data"] =$data;
		$result['status']=$status;
		$result['successMsg']=$successMsg;
		$result['errorMsg']=$errorMsg;
		
        // default return
        return $result;
    }

	public function getfiscalyear($arr)
	{
		$name ="";
		$sql = "SELECT * FROM FiscalMaster WHERE OrganizationId= ? ";
		$data = array();
		  $query = $this->db->prepare($sql);
		try{
			$query->execute(array($arr[1]));
			while($row = $query->fetch())
			{
				$StartDate = date("d-M-Y", strtotime($row->StartDate));
				$EndDate = date("d-M-Y", strtotime($row->EndDate));
				$data['year'] =$StartDate." to ".$EndDate;
				
			}
		}catch(Exception $e) {
			
		}
		return $data;
	}
	
	
	
	public function getovertime($arr)
	{
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		$mid = $arr[0];
		$orgid = $arr[1];
		//$userid = 4140;
		//$organization = 10;
		//$date = ($date=="")?date("Y-m-01") : date("Y-m-01");
		//$date1 = date("Y-m-01");
		$date1 =date("Y-m-01");
		//$lastday = date("Y-m-t", strtotime($date));
    	$brktime="";
					$attendancearr=array();
					$time = 0;
					$shifttime = 0;
					$overtime = 0;
					$totallatehrs=0;
					$totalearlyhrs=0;
					$totaltime=0;
					$totalshifttime=0;
					$totalovertimestring=0;
					$totalovertime="00:00";
					$arr=array();

					$sql1 = "SELECT TIME_FORMAT(TIMEDIFF(a.TimeOut, a.TimeIn),'%H:%i') as totaltime,TIME_FORMAT(TIMEDIFF(s.TimeOut, s.TimeIn),'%H:%i') as totalshifttime, TIME_FORMAT(TIMEDIFF(s.TimeOutBreak, s.TimeInBreak),'%H:%i') as breakTime, AttendanceDate, TIME_FORMAT((Overtime),'%H:%i') as otime,a.AttendanceStatus as asts,TIME_FORMAT(a.TimeIn,'%h:%i %p') as timein ,TIME_FORMAT(a.TimeOut,'%h:%i %p') as timeout,a.AttendanceDate as adate,device,((TIME_TO_SEC(s.TimeInGrace)-TIME_TO_SEC(a.TimeIn))/60 <0) as perc,((TIME_TO_SEC(s.TimeOutGrace)-TIME_TO_SEC(a.TimeOut))/60 <0) as lategracests,TIME_FORMAT(TIMEDIFF( a.TimeIn,s.TimeInGrace),'%H:%i') as latehrs ,TIME_FORMAT(TIMEDIFF( a.TimeOut,s.TimeOut),'%H:%i') as earlyhrs, a.TimeIn as TimeIn, a.TimeOut as TimeOut, s.TimeOutBreak as TimeOutBreak FROM AttendanceMaster a, ShiftMaster s where a.ShiftId= s.Id and EmployeeId=$mid  and  Month(AttendanceDate) = Month('$date1') and Year(AttendanceDate) = Year('$date1') and AttendanceDate!=CURDATE()   order by AttendanceDate";
					
					//SELECT  SEC_TO_TIME( SUM( TIME_TO_SEC( `Overtime` ) ) ) AS timeSum  
					// FROM AttendanceMaster where `EmployeeId`=4170 and Month(`AttendanceDate`) = Month("2018-08-08")

					$query1 = $this->db->prepare($sql1);
					//$query1->execute(array($mid,$date1,$date1));			
					$query1->execute();			
					
					$con=$query1->rowCount();
				
					while($row1 = $query1->fetch())
					{
						$latehrs=0;
						$earlyhrs=0;
						$res1=array();	
						$res=array();	
						
						$res1['sts'] = $row1->asts;					
						$res1['date'] = Utils::dateformatter($row1->adate);
						$timeoff=$this->getTimeoff($mid, $row1->adate);
						$res1['timeoff'] =($timeoff!=0)?$this->second_to_hhmm($timeoff):"-";

						$isearlytimeoff=$this->isearlyTimeoff($mid,$row1->adate,$row1->TimeIn);
						
						if($isearlytimeoff){
							//$timeoff=$timeoff+$this->getTimeoffearly($row->Id, $row1->adate,$row1->TimeIn);
							$timeoff=0;
						}
						$isearlytimeout=$this->isearlyTimeout($mid,$row1->adate,$row1->TimeOut);
						//echo $isearlytimeout;
						if($isearlytimeout){
							//$timeoff=$timeoff+$this->getTimeofflater($row->Id, $row1->adate,$row1->TimeOut);
							$timeoff=0;
						}
						
						//Utils::Trace($timeoff);
						$res1['device'] = $row1->device;
						$brktime=$res1['breakTime'] = $row1->breakTime;
						
						if($row1->asts==1 || $row1->asts==4 || $row1->asts==10 || $row1->asts==8 ) 
							$res1['overtime'] = $row1->otime;
						elseif( $row1->asts==5 || $row1->asts==3 ) //////public holiday/week off
							$res1['overtime'] = $row1->totaltime;
						else
							$res1['overtime'] = "00:00";
						
						
						if($row1->asts==1){
							// if($row1->perc==0){
								$latehrs =$this->explode_time($row1->latehrs);
							//}
							//if($row1->lategracests==0){
								$earlyhrs =$this->explode_time($row1->earlyhrs);
							//}
						}
						
						
						//$latehrs =$this->explode_time($row1->latehrs);
						$totallatehrs+=$latehrs;
						$totalearlyhrs+=$earlyhrs;
						
						$latehrshh=$this->second_to_hhmm($latehrs);
						//Utils::Trace($latehrshh);
						$res1['latehrs'] = $latehrshh;
						
						$earlyhrshh=$this->second_to_hhmm($earlyhrs);
						$res1['earlyhrs'] = $earlyhrshh;
						
						$res1['totaltime'] = "00:00";
						$res1['totalshifttime'] = "00:00";
						$res1['timein'] = "-";
						$res1['timeout'] = "-";	
						//if($row1->asts==1 || $row1->asts==4){
						if($row1->TimeIn!="00:00:00" && $row1->TimeOut!="00:00:00"){
							if($row1->asts==1 || $row1->asts==4 || $row1->asts==10){
								$res1['totalshifttime'] = $row1->totalshifttime;
								if($row1->asts==4 || $row1->asts==10){
									$res1['totaltime'] = $this->explode_time($row1->totaltime);
									$res1['overtime'] = $this->second_to_hhmm($res1['totaltime'] - (($this->explode_time($res1['totalshifttime']))/2));
								}
								else{
									//$res1['totaltime'] = $this->explode_time($row1->totaltime)-$this->explode_time($row1->breakTime)-$timeoff;
									$res1['totaltime'] = $this->explode_time($row1->totaltime)-$timeoff;
									//$res1['overtime'] = $this->second_to_hhmm($res1['totaltime'] - (($this->explode_time($res1['totalshifttime']))-$this->explode_time($row1->breakTime)));
								}
								$res1['totaltime'] = $this->second_to_hhmm($res1['totaltime']);
							}
							//$res1['totalshifttime'] = $row1->totalshifttime;
							$res1['timein'] = ($row1->TimeIn=="00:00:00")?"-":$row1->timein;						
							$res1['timeout'] = ($row1->TimeOut=="00:00:00")?"-":$row1->timeout;			
							
						}
						
						if($row1->TimeIn=="00:00:00" || $row1->TimeOut=="00:00:00"){
							$res1['totaltime'] = "00:00";
							$timeoff=0;
							//$latehrs +=$this->explode_time($row1->latehrs);
							$isearly=false;
						}	
						$time +=$this->explode_time($res1['totaltime']);
						if($row1->asts==4 || $row1->asts==10){
							
							$shifttime +=($this->explode_time($row1->totalshifttime))/2;//-$this->explode_time($brktime))/2;
						}
						elseif($row1->asts==1){
						
							$shifttime +=$this->explode_time($res1['totalshifttime'])-$this->explode_time($brktime);
						}
						else{
							$shifttime +=$this->explode_time($res1['totalshifttime']);
						}
						//commented because overtime is not entered exact//
												
						if($res1['overtime']!="00:00")
							$overtime = $overtime + $this->explode_time($res1['overtime']) -$timeoff;
						//$overtime =$time-$shifttime;
						$totaltime=$this->second_to_hhmm($time);						
						$totalshifttime=$this->second_to_hhmm($shifttime);						
						$totalovertime=$this->second_to_hhmm($overtime);
						
                       //$attendancearr=$res;						
					}
						if($totalovertime < '00:00')
						{
							
							//$arr['overtime']="Total Undertime";
						//	$arr['totalovertimestring']=$totalovertime;
							
							 $totalovertimestring = $totalovertime;
						}
						else{
							
							//$arr['overtime']="Total Overtime";
							//$arr['totalovertimestring']=$totalovertime;
							 $totalovertimestring = $totalovertime;
						}
						//Utils::Trace($totalovertime);
						//$attendancearr[]=$res1;
						
						if($totalovertimestring>"00:00"){
						$result['otime']=$totalovertimestring;
						
						}
						if($totalovertimestring<"00:00"){
							$totalovertimestring = preg_replace('/-/', ' ', $totalovertimestring);
						$result['utime']= $totalovertimestring;
						//$data['utime']= '00:00';
						}
		return $result;
		
    }
	
	
	
/* public function getotime($request)
	  {
    
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		$userid = strtolower($request[0]);
		$orgid = $request[1];
		//$userid = 4140;
		//$organization = 10;
    	

		$sql = "SELECT * FROM HolidayMaster WHERE OrganizationId = ? AND DateFrom>=CURDATE() and find_in_set($userdesig,DivisionId)  order by DateFrom asc  limit 7";

        $query = $this->db->prepare($sql);
		$query->execute(array(  $orgid ));
		$count =  $query->rowCount();
			if($count>=1)
			{
				while($row = $query->fetch())
				{
					

					$data[] = $res;

				}
			}
		
		return $data;
		//return $data['forthcomingevents'][0]['name'];
    }*/
	
	
	
	public function getTimeoff($empid, $date)
	{
		$name =0;
		$sql = "SELECT TIME_FORMAT(TIMEDIFF(Timeto, TimeFrom),'%H:%i') as totaltime FROM Timeoff WHERE EmployeeId = $empid and TimeofDate ='$date' and ApprovalSts in (2,6)";
        $query = $this->db->prepare($sql);
		try{
			$query->execute();
			while($row = $query->fetch())
			{
				$name += $this->explode_time($row->totaltime);
			}
			//Utils::Trace($name);
		}catch(Exception $e) {
			
		}
		return $name;
	}
	public function isearlyTimeoff($empid, $date,$timein)
	{
		$name =0;
		$sql = "SELECT * FROM Timeoff WHERE EmployeeId = $empid and TimeofDate ='$date' and TimeFrom<'$timein'";
        $query = $this->db->prepare($sql);
		try{
			$query->execute();
			$con=$query->rowCount();
			if($con>=1)
			{
				return true;
			}
			//Utils::Trace($name);
		}catch(Exception $e) {
			
		}
		return false;
	}
	
	
	public function isearlyTimeout($empid,$date,$timeout)
	{
		$name =0;
		$sql = "SELECT * FROM Timeoff WHERE EmployeeId = $empid and TimeofDate = '$date' and TimeTo > '$timeout'";
        $query = $this->db->prepare($sql);
		try{
			$query->execute();
			$con=$query->rowCount();
			if($con>=1)
			{
				return true;
			}
			//Utils::Trace($name);
		}catch(Exception $e) {
			
		}
		return false;
	}
	
	 public function second_to_hhmm($time) { //convert seconds to hh:mm
	
	if($time < 0){
		$hours = trim($time, "-");
		$hour = floor($hours / 60);
		$hour = "-".$hour ;
	
		//$hour = $hour-1;
	}else if($time==0){
		$hour = "00";
	}else{
        $hour = floor($time / 60);
	}
		//Utils::Trace($hour);
        $minute = strval(floor($time % 60));
		//Utils::Trace($minute);
        if ($minute == 0) {
            $minute = "00";
        }
		elseif($minute < 0)
		{
			$minute = trim($minute, "-");
		}
		else {
            $minute = $minute;
        }
	
		if($minute < 10 && $minute != 00)
			$minute = "0".$minute;
			if($time==0){
				$minute="00";
			}
        $time = $hour . ":" . $minute;
		//print_r(  $time);
		//Utils::Trace($time);
        return $time;
		
	}

	public function getSalarysummary($arr)
	{
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array(); 
		$userid  = $arr[0];
        $orgid=$arr[1];		//USER ID CONTAINS IN ARRAY FIRST VALUE;
		$all=$arr[2];
		//$stsn=$this->getstsid($arr[2],'LeaveStatus');
		//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$sts=1;
		$hrsts=0;
		//$ids = Utils::getReportingIds($userid, $this->db);
		$sWhere = "";
		
		$query = $this->db->prepare("SELECT Currency FROM Organization WHERE Id = ?");
		$currency="";
		$query->execute(array($orgid));
		if ($query->rowCount()>0) {
			$emp = $query->fetch();
			$query12 = $this->db->prepare("SELECT CurrencyCode, CurrencyImage FROM CurrencyMaster WHERE Id = ?");
			$query12->execute(array( $emp->Currency));
			while($emp1 = $query12->fetch())
			{
				$currency=$emp1->CurrencyCode;
				
			}	
		}
		$cur=Utils::getCurrencySymbolHex($currency);
		if($hrsts==1)
		{
			$sWhere = " WHERE  OrganizationId= $orgid and HoldStatus!=1 and FinalStatus=1 and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0)";
		}
		else
		{ 
		if($all!=""){
			
			$ids =Utils::getReportingIds($userid, $this->db,$orgid);
			$sWhere = " Where (EmployeeId in ($ids) AND OrganizationId= $orgid and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0)) and FinalStatus=1 and HoldStatus!=1 ";
		}
			else{$sWhere = " Where (EmployeeId =$userid AND OrganizationId= $orgid and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0)) and FinalStatus=1 and HoldStatus!=1 ";}
			
		}
		
		$present =0;$absent =0;$leave =0;$total =0;$month=0;
		$sql1 = "SELECT * FROM  SalaryMaster $sWhere ORDER BY  SalaryMonth desc ";
		$query1 = $this->db->prepare($sql1);
		$query1->execute();
		$total = $query1->rowCount();
		
		if($total > 0)
		{
			while($row1 = $query1->fetch())
			{
				$res = array();
				//$res['id'] = $row1->Id;
				//$res['id'] = Utils::getName($row1->EmployeeIds,'EmployeeMaster','FirstName',$this->db);
				$res['Id'] = Utils::encode5t($row1->Id);
				$res['name'] = $this->getName($row1->EmployeeId);
				$res['PaidDays'] = $row1->PaidDays;
				//$val1=str_replace($search, $replace, $subject); 
				$val=str_replace("-","",$row1->EmployeeCTC);
				
				$res['EmployeeCTC'] = (string)number_format($val);
				 
				$res['SalaryMonth'] = date("M Y", strtotime($row1->SalaryMonth));
				$res['currency'] = $currency;
				
				$data[] = $res;
			}
		}
		return $data ;
		//return $data[1]["LeaveStatus"] ;
	}
	
	public function getName1($id, $tablename, $column='Name')
	{
		$name ="";
		$sql = "SELECT $column FROM $tablename WHERE Id = :id";
        $query = $this->db->prepare($sql);
		try{
			$query->execute(array(':id' => $id ));
			while($row = $query->fetch())
			{
				$name = ucwords(strtolower($row->$column));
			}
		}catch(Exception $e) {
			
		}
		return $name;
	}
	
	public function getPayrollSummary($arr)
	{
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array(); 
		$userid  = $arr[0];
        $orgid=$arr[1];		//USER ID CONTAINS IN ARRAY FIRST VALUE;
		$all=$arr[2];
		//$stsn=$this->getstsid($arr[2],'LeaveStatus');
		//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$sts=1;
		$hrsts=0;
		//$ids = Utils::getReportingIds($userid, $this->db);
		$sWhere = "";
		
		$query = $this->db->prepare("SELECT Currency FROM Organization WHERE Id = ?");
		$currency="";
		$query->execute(array($orgid));
		if ($query->rowCount()>0) {
			$emp = $query->fetch();
			$query12 = $this->db->prepare("SELECT CurrencyCode, CurrencyImage FROM CurrencyMaster WHERE Id = ?");
			$query12->execute(array( $emp->Currency));
			while($emp1 = $query12->fetch())
			{
				$currency=$emp1->CurrencyCode;
				
			}	
		}
		$cur=Utils::getCurrencySymbolHex($currency);
		if($hrsts==1)
		{
			$sWhere = " WHERE  OrganizationId= $orgid and HoldStatus!=1 and FinalStatus=1 and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0)";
		}
		else
		{ 
		if($all!=""){
			$ids =$this->getReportingIds($userid, $orgid);
			$sWhere = " Where (EmployeeId in ($ids) AND OrganizationId= $orgid and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0)) and FinalStatus=1 and HoldStatus!=1 ";
		}
			else{$sWhere = " Where (EmployeeId =$userid AND OrganizationId= $orgid and EmployeeId in (select Id from EmployeeMaster where Is_Delete=0)) and FinalStatus=1 and HoldStatus!=1 ";}
			
		}
		
		$present =0;$absent =0;$leave =0;$total =0;$month=0;
		$sql1 = "SELECT * FROM PayrollMaster $sWhere ORDER BY SalaryMonth desc ";
		$query1 = $this->db->prepare($sql1);
		$query1->execute();
		$total = $query1->rowCount();
		
		if($total > 0)
		{
			while($row1 = $query1->fetch())
			{
				$res = array();
				//$res['id'] = $row1->Id;
				//$res['id'] = Utils::getName($row1->EmployeeIds,'EmployeeMaster','FirstName',$this->db);
				$res['Id'] = Utils::encode5t($row1->Id);
				$res['name'] = $this->getName($row1->EmployeeId);
				$res['PaidDays'] = $row1->PaidDays;
				//$val1=str_replace($search, $replace, $subject); 
				$val=str_replace("-","",$row1->EmployeeCTC);
				
				$res['EmployeeCTC'] = (string)number_format($val);
				 
				$res['SalaryMonth'] = date("M Y", strtotime($row1->SalaryMonth));
				$res['currency'] = $currency;
				
				$data[] = $res;
			}
		}
		return $data ;
		//return $data[1]["LeaveStatus"] ;
	}
		
	public function getPayrollDetail($request)
    {
		$result = array();
		$count=0;$c=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		
		$mid   = $request[0];	//USER ID CONTAINS IN ARRAY FIRST VALUE;
		$orgid = $request[1];	//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$salaryid = Utils::decode5t($request[2]); //SALARY ID CONTAINS IN ARRAY THIRD VALUE;
		
		$bankname =""; $bankcode =""; $bankacct =0;
        $sql = "SELECT * FROM PayrollMaster WHERE Id = :id";
        $query = $this->db->prepare($sql);
		try{
			$query->execute(array(':id' => $salaryid ));
			$count =  $query->rowCount();
		}catch(Exception $e) {
			$errorMsg = 'Message: ' .$e->getMessage();
		}
		if($count>=1)
		{
			$status=true;
			$successMsg=$count." record found";
			while($row = $query->fetch())
			{
				$res = array();
				$res['salaryid'] = $row->Id;
				$res['employee'] = $row->EmployeeId;
				$empid= $row->EmployeeId;
				
				$month=$res['empmonthdate'] = $row->SalaryMonth;
				$empdivision=Utils::getName($row->EmployeeId,"EmployeeMaster",'DIvision',$this->db);
				$divcur= Utils::getDivisioncurrency($empdivision,$this->db);
				$res['empcurency'] = $divcur;
				$res['emppaiddays'] = (int)$row->PaidDays;
				$res['desc'] = $row->Description;
				$res['empmonthname'] = date("F Y", strtotime($row->SalaryMonth));
				$bankname = $row->BankName;
				$bankcode = Utils::decode5t($row->BankCode); 
				$bankacct = Utils::decode5t($row->BankAccountNo);
				$agentid = $row->agent_id;
				
				$res['salarydetail'] = array();
				$headarray = array();
				
				$res['salarydetail1'] = array();
				$headarray1 = array();
				$total=0;$totaldeduction=0;
				$total1=0;$totaldeduction1=0;
				if($bankname!=''){
				 $res['bankname'] = $bankname;
				 $res['bankcode'] = $bankcode;
				 $res['bankacct'] = $bankacct;
				}else{
					$sql2 = "SELECT BankId,IBAN FROM EmployeeBankDetails WHERE EmployeeId = ? and BankStatus = 1";
					$query2 = $this->db->prepare($sql2);
					$query2->execute(array( $row->EmployeeId ));
					if($row2 = $query2->fetch()){
						$bankid=$row2->BankId;
						$res['bankname'] = Utils::getName($bankid,"BankMaster",'Name',$this->db);
						$res['bankcode'] = Utils::getName($bankid,"BankMaster",'Code',$this->db);
						$res['bankacct'] = Utils::decode5t($row2->IBAN);$res['branch'] = Utils::decode5t($row2->Branch);
					}
				}
				
				$sql2 = "SELECT DocumentNumber FROM EmployeeDocument WHERE EmployeeId = ? and OrganizationId=? and DocumentTypeId IN(SELECT Id From DocumentMaster WHERE OrganizationId=? and Name='PAN Card')";
				$query2 = $this->db->prepare($sql2);
				$query2->execute(array( $row->EmployeeId,$orgid,$orgid ));
				$c =  $query2->rowCount();
				if($c>0){
					$row3 = $query2->fetch();
					$res['pan_no'] = Utils::decode5t($row3->DocumentNumber);
				}else{
					$res['pan_no'] = "";
				}
				$res['agentid'] = $agentid;
				$holdsts=$res['holdsts'] = $row->HoldStatus;
				
				$number=$this->getSalaryPaidDays($orgid,"PaidDays");
				if($number==0 || $number==1)
					$number = cal_days_in_month(CAL_GREGORIAN, date('m', strtotime($month)), date('Y', strtotime($month)));
				$res['totaldaysinmonth'] = $number;
				$res['leavedays'] = $number - (int)$row->PaidDays;
				//echo $res['leavedays'];
				
				////////////////   FIND OUT THE COMPANY DETAILS ARE AVAILABLE  /////////////////////////////				
				$sql1 = "SELECT Name,Logo FROM Organization WHERE Id = ?";
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array( $row->OrganizationId ));
				while($row1 = $query1->fetch())
				{
					$res['companyname'] = $row1->Name;
					$res['logo'] = URL."public/uploads/".$row->OrganizationId."/".$row1->Logo;
				}
				
				////////////////   FIND OUT THE EMPLOYEE DETAILS ARE AVAILABLE  /////////////////////////////				
				$sql1 = "SELECT * FROM EmployeeMaster WHERE Id = ? and OrganizationId = ?";
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array( $row->EmployeeId, $orgid ));
				while($row1 = $query1->fetch())
				{
					$res['id'] = (float)$row1->Id;
					$res['name'] = ucwords(strtolower($row1->FirstName." ".$row1->LastName));
					$res['empcode'] = $row1->EmployeeCode;
					$res['empdivision'] = $this->getName($row1->Division,'DivisionMaster');
					$res['emplocation'] = $this->getName($row1->Location,'LocationMaster');
					$res['empdept'] = $this->getName($row1->Department,'DepartmentMaster');
					$res['empdesig'] = $this->getName($row1->Designation,'DesignationMaster');
					$res['empshift'] =$this->getName( $row1->Shift,'ShiftMaster');
					$res['empgrade'] = $this->getName($row1->Grade,'GradeMaster');
					$res['empgross'] = $row1->EmployeeCTC;
					$res['empctc'] =round (($row1->AnnualCTC)/12);
					$res['uan'] = $row1->uan;
					$res['esi_no'] = $row1->esi_no;
					$res['pf_no'] = $row1->pf_no;
					//echo $res['pf_no'] = $row1->pf_no;
					
					////////////////////////////////// CHECKING FOR OLD DATA, IF WE CHECK OLD SALARY OR PAY SLIP, THERE MUST BE OLD DATA NOT THE CURRENT ONE, SO WE ARE FETHING THE DATA FROM JOB MODIFICATION TABLE//////////////////////////////////
					
					$sqljob="SELECT * FROM JobModificationMaster where EmployeeId=? ";
					$queryjob=$this->db->prepare($sqljob);
					$queryjob->execute(array($row->EmployeeId));
					while($rowjob = $queryjob->fetch())
					{
						if(strtotime($rowjob->ApplyFrom ) > strtotime($month))
						{
							$sqljob1="SELECT * FROM JobModificationChild where JobId=? ";
							$queryjob1=$this->db->prepare($sqljob1);
							$queryjob1->execute(array($rowjob->Id));
							while($rj = $queryjob1->fetch())
							{
								if($rj->FieldName == "Department")
									$res['empdept'] = $this->getName($rj->OldValue,'DepartmentMaster');
								if($rj->FieldName == "Designation")
									$res['empdesig'] = $this->getName($rj->OldValue,'DesignationMaster');
								if($rj->FieldName == "Division")
									$res['empdivision'] = $this->getName($rj->OldValue,'DivisionMaster');
								if($rj->FieldName == "EmployeeCTC")
									$res['empctc'] = $rj->OldValue;
								if($rj->FieldName == "Grade")
									$res['empgrade'] = $this->getName($rj->OldValue,'GradeMaster');
							}	
						}
						
					}
					
				}
				
				////////////////   FIND OUT THE SALARY DEDUCTION HEADS ARE AVAILABLE  /////////////////////////////	
                $sql1 = "SELECT *, ph.type as type FROM payrollHeads ph, PayrollChild pc where ph.type=4 and ph.id=pc.HeadId and ph.active_sts='Active' and pc.salaryid=? and ph.permission_sts='1' and ph.OrganizationId=?";				
				/* $sql1 = "SELECT *, sh.headtype as type FROM SalaryHead sh, SalaryChild sc where sc.headtype=1 and sc.headid=sh.id and salaryid=?"; */
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array( $salaryid,$orgid ));
				while($row1 = $query1->fetch())
				{
					$res1 = array();
					//$res1['id'] = (int)$row1->Id;
					$res1['id'] = (int)$row1->id;
					$res1['amount'] = (int)$row1->HeadAmount;					
					//$res1['name'] =(($row1->type==4)?"(-) ":"(+) ").$row1->earning_name;
					$res1['name'] =$row1->earning_name;
					$res1['type'] = (int)$row1->type;
					//$res1['headtype'] = (int)($row1->type==3)?0:$row1->type;
					$res1['headtype'] =(int)$row1->type;
					$res1['payslip'] = "";
					$res1['is_variable'] = "";
					//$res1['additions'] = ($row1->type==3)?0:1;
					$res1['additions'] = (int)$row1->type;
					$headarray[]=$res1;
					if($row1->type==4)
					$totaldeduction += (int)$row1->HeadAmount;
					else
					$total += (int)$row1->HeadAmount;
				}
				///////////// LIST OUT THE OTHER SALARY HEAD FIXED FOR ALL ORGANISATION AND APPLY TO SALARY ////////////////////////	
	             
				// $sql1 = "SELECT * FROM payrollHeads ph, PayrollChild pc where  ph.type=1 and ph.id=pc.HeadId and pc.salaryid=? and ph.active_sts='Active' and ph.permission_sts='1' and ph.OrganizationId=?";
				
				 $sql1 = "SELECT * FROM payrollHeads ph, PayrollChild pc ,payroll_category cc where  ph.type=1 and ph.id=pc.HeadId and pc.salaryid=? and ph.active_sts='Active' and ph.permission_sts='1' and ph.OrganizationId=? and ph.category_id=cc.id order by cc.id asc";
				/* $sql1 = "SELECT * FROM SalaryOtherHead sh, SalaryChild sc where sc.headtype=2 and sc.headid=sh.HeadCode and sc.salaryid=? and sh.OrganizationId=? "; */
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array( $salaryid,$orgid));
				while($row1 = $query1->fetch())
				{
					$res1 = array();
					$res1['id'] = $row1->id;
					$res1['amount'] = (int)$row1->HeadAmount;
					//$res1['name'] =(($row1->type==1)?"(+) ":"(-) ").$row1->earning_name;
					$res1['name'] =$row1->earning_name;
					$res1['type'] = 1;
					$res1['headtype'] = (int)$row1->type;
					$res1['is_variable'] = $row1->is_variable;
					
					$res1['payslip'] = "";
					//$res1['additions'] = ($row1->Type==0)?0:1;
					$res1['additions'] = (int)$row1->type;
					$headarray[]=$res1;
					
					if($row1->type)
					$total += (int)$row1->HeadAmount;
					else
					$totaldeduction += (int)$row1->HeadAmount;
				}
				
				//echo $total1;
				///////// for getting amount according to gross salary
				///where head type is Fixed
				///
				
				 $sql2 = "SELECT * FROM payrollHeads ph,  EmployeePayrollDetails pc ,payroll_category cc where  ph.type=1 and ph.id=pc.HeadId  and ph.active_sts='Active' and ph.permission_sts='1' and ph.OrganizationId=? and ph.is_variable=? and ph.category_id=cc.id  and EmployeeId =? order by cc.id asc";
				
				$query2 = $this->db->prepare($sql2);
				$query2->execute(array($orgid,'false', $empid));
				while($row2 = $query2->fetch())
				{
					$res2 = array();
					$res2['id'] = $row2->id;
					$res2['amount'] = (int)$row2->HeadAmount;
					$res2['name'] =$row2->earning_name;
					$res2['type'] = 1;
					$res2['headtype'] = (int)$row2->type;
					$res2['is_variable'] = $row2->is_variable;
					$res2['payslip'] = "";
					$res2['additions'] = (int)$row2->type;
					$headarray1[]=$res2;
					
					if($row2->type ==1)
					$total1 += (int)$row2->HeadAmount;
					else
					$totaldeduction1 += (int)$row2->HeadAmount;
				}
				
				//
				///////// for getting amount according to gross salary
				///where head type is variable
				///
				
				$sql2 = "SELECT * FROM payrollHeads ph, PayrollChild pc ,payroll_category cc where  ph.type=1 and ph.id=pc.HeadId and pc.salaryid=? and ph.active_sts='Active' and ph.permission_sts='1' and ph.OrganizationId=? and ph.is_variable=? and ph.category_id=cc.id order by cc.id asc";
				
				$query2 = $this->db->prepare($sql2);
				$query2->execute(array( $salaryid,$orgid,'true'));
				while($row2 = $query2->fetch())
				{
					$res2 = array();
					$res2['id'] = $row2->id;
					
					$res2['amount'] = (int)$row2->HeadAmount;
					 
					$res2['name'] =$row2->earning_name;
					$res2['type'] = 1;
					$res2['headtype'] = (int)$row2->type;
					$res2['is_variable'] = $row2->is_variable;
					$res2['payslip'] = "";
					$res2['additions'] = (int)$row2->type;
					$headarray1[]=$res2;
					
					if($row2->type == 1)
					$total1 += (int)$row2->HeadAmount;
					else
					$totaldeduction1 += (int)$row2->HeadAmount;
				}
				
				///////// for getting amount according to gross salary
				///where head  is  deduction
				///
				// for getting pf amount
				$fixed_pf_amount = 0;
				$variable_pf_amount = 0;
				$total_pf_amount = 0;
				$pf_amt = 0;
				$sql7 = "SELECT Id FROM PayrollPFSettings  where 	PfSts=? and  OrganizationId=?";
				$query7 = $this->db->prepare($sql7);
				$query7->execute(array(1, $orgid));
				$count7 =  $query7->rowCount();
				if($count7==1)
				{
					
					$sql8 = "SELECT applicable_pf FROM EmployeeMaster  where Id=? and  OrganizationId=?";
					$query8 = $this->db->prepare($sql8);
					$query8->execute(array($empid, $orgid));
					while($row8 = $query8->fetch())
				   {
					   if($row8->applicable_pf == 1)
					   {  
				   
				   
							$sql3 = "SELECT * FROM payrollHeads  where type=? and active_sts=? and permission_sts=? and OrganizationId=? and include_for_epf =?";
							$query3 = $this->db->prepare($sql3);
							$query3->execute(array('1','Active','1', $orgid,'true'));
							$count3 =  $query3->rowCount();
				  
							while($row3 = $query3->fetch())
							{
								
								if($row3->is_variable == 'false')
								{ 
									
									 $sql4 = "SELECT * FROM  EmployeePayrollDetails where HeadType=? and EmployeeId=?  and OrganizationId=? and HeadId =? ";
									 $query4 = $this->db->prepare($sql4);
									 $query4->execute(array(1,$empid, $orgid, $row3->id));	
									 while($row4 = $query4->fetch())
									{ 
									  
									   $fixed_pf_amount += round($row4->HeadAmount);
										  
									}
									
								}
								if($row3->is_variable == 'true')
								{
									
									$sql4 = "SELECT * FROM  PayrollChild where HeadType=? and HeadId=? and SalaryId=?";
									 $query4 = $this->db->prepare($sql4);
									 $query4->execute(array(1,$row3->id,$salaryid));	
									 while($row4 = $query4->fetch())
									{ 
									  
									  $variable_pf_amount += round($row4->HeadAmount);
										  
									}
								}
								
							}
				
				
							$total_pf_amount = $fixed_pf_amount +$variable_pf_amount;
							$pf_amt = round($this->getEmployeePF($total_pf_amount ,1, $orgid),0);
					   }
				   }
				}
				
				
				$sql2 = "SELECT * FROM payrollHeads ph, PayrollChild pc ,payroll_category cc where  ph.type=4 and ph.id=pc.HeadId and pc.salaryid=? and ph.active_sts='Active' and ph.permission_sts='1' and ph.OrganizationId=?  and ph.category_id=cc.id";
				
				$query2 = $this->db->prepare($sql2);
				$query2->execute(array( $salaryid,$orgid));
				while($row2 = $query2->fetch())
				{
					$amount=0;
					$res2 = array();
					$res2['id'] = $row2->id;
					if($row2->category_id == 35)
					{
						$res2['amount'] = $pf_amt;
						$amount =$pf_amt;
					}
					else{
						$res2['amount'] = (int)$row2->HeadAmount;
						$amount =(int)$row2->HeadAmount;
					}
					
					$res2['name'] =$row2->earning_name;
					$res2['type'] = 4;
					$res2['headtype'] = (int)$row2->type;
					$res2['is_variable'] = "";
					$res2['payslip'] = "";
					$res2['additions'] = (int)$row2->type;
					$headarray1[]=$res2;
					
					if($row2->type == 4)
					$totaldeduction1 += (int)$amount;
					
					else
						$total1 += (int)$amount;
					
				}

				$res['salarydetail1'] = $headarray1;
				$res['salarydetail'] = $headarray;
				$res['emptotal'] = round($total);
				$res['emptotal1'] = round($total1);
				$res['emptotaldeduction'] =round($totaldeduction);
				$res['emptotaldeduction1'] =round($totaldeduction1);
				$res['netpay'] = round($total-$totaldeduction);
				
				$data[] = $res;
			}
        }
		
		if ($count == 1) {
           $status =true;
		   $successMsg = SALARYMASTER_MODULE_GETDETAIL_SUCCESS;
        } else {
           $status =false;
		   $errorMsg=SALARYMASTER_MODULE_GETDETAIL_FAILED;
        }
		$result["data"] =$data;
		$result['status']=$status;
		$result['currentdate']=Utils::dateformatter(date("Y-m-d"));
		$result['successMsg']=$successMsg;
		$result['errorMsg']=$errorMsg;
		return $result;
    }
	
	public function getEmployeePF($pfamt,$empPfSts,$orgid)
	{
		
		$sql="select * from PayrollPFSettings where OrganizationId=$orgid";

		$query = $this->db->prepare($sql);
		$query->execute(array());
		$pf=0;
		if($row=$query->fetch()){
	
			$employee_pf=$row->employee_contribution_rate;
		    
			if($empPfSts == 1)
			{
		         
				if($employee_pf == "true" && $pfamt >= 15000)
				{
				   $pf =(15000*(int)$row->employee_amount)/100;
				}
				elseif($employee_pf == "true")
				{
					$pf =round(($pfamt*(int)$row->employee_amount)/100);
				}
					
				else{
					//echo "hii";
					$pf =round(($pfamt*(int)$row->employee_amount)/100);
				}
				/* else{
					$pf=0;
			    } */
			}
			else{
				$pf=0;
			}
			
		}
		
		return $pf;
	}
	
	public function getPayrollPaidDays($orgid,$field)
	{
		$value=0;
		try{
			$sql1 = "SELECT $field FROM PayrollPaidDays WHERE OrganizationId=?";
			$query1 = $this->db->prepare($sql1);
			$query1->execute(array( $orgid));
			while($row1 = $query1->fetch())
			{
				$value=$row1->$field	;	
			}
		}catch(Exception $e) {
			
		}		
		return $value;
	}
	
	public function getSalaryDetail($request)
    {
		$result = array();
		$count=0;$c=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		$divcur="";
		$mid   = $request[0];	//USER ID CONTAINS IN ARRAY FIRST VALUE;
		$orgid = $request[1];	//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$query = $this->db->prepare("SELECT Currency FROM Organization WHERE Id = ?");
		$currency="";
		$query->execute(array($orgid));
		if ($query->rowCount()>0) {
			$emp = $query->fetch();
			$query12 = $this->db->prepare("SELECT CurrencyCode, CurrencyImage FROM CurrencyMaster WHERE Id = ?");
			$query12->execute(array( $emp->Currency));
			while($emp1 = $query12->fetch())
			{
				$currency=$emp1->CurrencyCode;
				
			}	
		}
		$cur=Utils::getCurrencySymbolHex($currency);
		$salaryid = Utils::decode5t($request[2]);	//SALARY ID CONTAINS IN ARRAY THIRD VALUE;
		
		$bankname =""; $bankcode =""; $bankacct =0;
        $sql = "SELECT * FROM SalaryMaster WHERE Id = :id";
        $query = $this->db->prepare($sql);
		try{
			$query->execute(array(':id' => $salaryid ));
			$count =  $query->rowCount();
		}catch(Exception $e) {
			$errorMsg = 'Message: ' .$e->getMessage();
		}
		if($count>=1)
		{
			$status=true; 
			$successMsg=$count." record found";
			while($row = $query->fetch())
			{
				$res = array();
				$res['salaryid'] = $row->Id;
				$res['employee'] = $row->EmployeeId;
				$month=$res['empmonthdate'] = $row->SalaryMonth;
				$empdivision=self::getName1($row->EmployeeId,"EmployeeMaster",'DIvision',$this->db);
				$divcur= Utils::getDivisioncurrency($empdivision,$this->db);
				$res['empcurency'] = $divcur;
				$res['emppaiddays'] = (int)$row->PaidDays;
				$res['desc'] = $row->Description;
				$res['empmonthname'] = date("F Y", strtotime($row->SalaryMonth));
				$bankname = $row->BankName;
				$bankcode = Utils::decode5t($row->BankCode); 
				$bankacct = Utils::decode5t($row->BankAccountNo);
				$agentid = $row->agent_id;
				
				$res['salarydetail'] = array();
				$headarray = array();
				$total=0;$totaldeduction=0;
				if($bankname!=''){
				 $res['bankname'] = $bankname;
				 $res['bankcode'] = $bankcode;
				 $res['bankacct'] = $bankacct;
				}else{
					$sql2 = "SELECT BankId,IBAN FROM EmployeeBankDetails WHERE EmployeeId = ? and BankStatus = 1";
					$query2 = $this->db->prepare($sql2);
					$query2->execute(array( $row->EmployeeId ));
					if($row2 = $query2->fetch()){
						$bankid=$row2->BankId;
						$res['bankname'] = Utils::getName1($bankid,"BankMaster",'Name',$this->db);
						$res['bankcode'] = Utils::getName1($bankid,"BankMaster",'Code',$this->db);
						$res['bankacct'] = Utils::decode5t($row2->IBAN);
					}
				}
				
				$sql2 = "SELECT DocumentNumber FROM EmployeeDocument WHERE EmployeeId = ? and OrganizationId=? and DocumentTypeId IN(SELECT Id From DocumentMaster WHERE OrganizationId=? and Name='PAN Card')";
				$query2 = $this->db->prepare($sql2);
				$query2->execute(array( $row->EmployeeId,$orgid,$orgid ));
				$c =  $query2->rowCount();
				if($c>0){
					$row3 = $query2->fetch();
					$res['pan_no'] = Utils::decode5t($row3->DocumentNumber);
				}else{
					$res['pan_no'] = "";
				}
				$res['agentid'] = $agentid;
				$holdsts=$res['holdsts'] = $row->HoldStatus;
				
				$number=$this->getSalaryPaidDays($orgid,"PaidDays");
				if($number==0 || $number==1)
					$number = cal_days_in_month(CAL_GREGORIAN, date('m', strtotime($month)), date('Y', strtotime($month)));
				$res['totaldaysinmonth'] = $number;
				$res['leavedays'] = $number - (int)$row->PaidDays;
				
				
				////////////////   FIND OUT THE COMPANY DETAILS ARE AVAILABLE  /////////////////////////////				
				$sql1 = "SELECT Name,Logo FROM Organization WHERE Id = ?";
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array( $row->OrganizationId ));
				while($row1 = $query1->fetch())
				{
					$res['companyname'] = $row1->Name;
					$res['logo'] = URL."public/uploads/".$row->OrganizationId."/".$row1->Logo;
				}
				
				////////////////   FIND OUT THE EMPLOYEE DETAILS ARE AVAILABLE  /////////////////////////////				
				$sql1 = "SELECT * FROM EmployeeMaster WHERE Id = ? and OrganizationId = ?";
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array( $row->EmployeeId, $orgid ));
				while($row1 = $query1->fetch())
				{
					$res['id'] = (float)$row1->Id;
					$res['name'] = ucwords(strtolower($row1->FirstName." ".$row1->LastName));
					$res['empcode'] = $row1->EmployeeCode;
					$res['empdivision'] = $this->getName1($row1->Division,'DivisionMaster');
					$res['empdept'] = $this->getName1($row1->Department,'DepartmentMaster');
					$res['empdesig'] = $this->getName1($row1->Designation,'DesignationMaster');
					$res['empshift'] =$this->getName1( $row1->Shift,'ShiftMaster');
					$res['empgrade'] = $this->getName1($row1->Grade,'GradeMaster');
					$res['empgross'] = $row1->EmployeeCTC;
					$res['empctc'] = $row1->AnnualCTC;
					$res['uan'] = $row1->uan;
					$res['esi_no'] = $row1->esi_no;
					$res['pf_no'] = $row1->pf_no;
					
					////////////////////////////////// CHECKING FOR OLD DATA, IF WE CHECK OLD SALARY OR PAY SLIP, THERE MUST BE OLD DATA NOT THE CURRENT ONE, SO WE ARE FETHING THE DATA FROM JOB MODIFICATION TABLE//////////////////////////////////
					
					$sqljob="SELECT * FROM JobModificationMaster where EmployeeId=? ";
					$queryjob=$this->db->prepare($sqljob);
					$queryjob->execute(array($row->EmployeeId));
					while($rowjob = $queryjob->fetch())
					{
						if(strtotime($rowjob->ApplyFrom ) > strtotime($month))
						{
							$sqljob1="SELECT * FROM JobModificationChild where JobId=? ";
							$queryjob1=$this->db->prepare($sqljob1);
							$queryjob1->execute(array($rowjob->Id));
							while($rj = $queryjob1->fetch())
							{
								if($rj->FieldName == "Department")
									$res['empdept'] = $this->getName1($rj->OldValue,'DepartmentMaster');
								if($rj->FieldName == "Designation")
									$res['empdesig'] = $this->getName1($rj->OldValue,'DesignationMaster');
								if($rj->FieldName == "Division")
									$res['empdivision'] = $this->getName1($rj->OldValue,'DivisionMaster');
								if($rj->FieldName == "EmployeeCTC")
									$res['empctc'] = $rj->OldValue;
								if($rj->FieldName == "Grade")
									$res['empgrade'] = $this->getName1($rj->OldValue,'GradeMaster');
							}	
						}
						
					}
					
				}
				
				////////////////   FIND OUT THE SALARY DEDUCTION HEADS ARE AVAILABLE  /////////////////////////////				
				$sql1 = "SELECT *, sh.headtype as type FROM SalaryHead sh, SalaryChild sc where sc.headtype=1 and sc.headid=sh.id and salaryid=?";
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array( $salaryid ));
				while($row1 = $query1->fetch())
				{
					$res1 = array();
					$res1['id'] = (int)$row1->Id;
					$res1['amount'] = (int)$row1->HeadAmount;					
					$res1['name'] =(($row1->type==3)?"(-) ":"(+) ").$row1->Name;
					$res1['type'] = 1;
					$res1['headtype'] = (int)($row1->type==3)?0:$row1->type;
					$res1['payslip'] = (int)$row1->IncludeInPayslip;
					$res1['additions'] = ($row1->type==3)?0:1;
					$headarray[]=$res1;
					if($row1->type==3)
					$totaldeduction += (int)$row1->HeadAmount;
					else
					$total += (int)$row1->HeadAmount;
				}
				///////////// LIST OUT THE OTHER SALARY HEAD FIXED FOR ALL ORGANISATION AND APPLY TO SALARY ////////////////////////	
	
				$sql1 = "SELECT * FROM SalaryOtherHead sh, SalaryChild sc where sc.headtype=2 and sc.headid=sh.HeadCode and sc.salaryid=? and sh.OrganizationId=? ";
				$query1 = $this->db->prepare($sql1);
				$query1->execute(array( $salaryid,$orgid));
				while($row1 = $query1->fetch())
				{
					$res1 = array();
					$res1['id'] = $row1->HeadCode;
					$res1['amount'] = (int)$row1->HeadAmount;
					$res1['name'] =(($row1->Type==0)?"(-) ":"(+) ").$row1->Name;
					$res1['type'] = 2;
					$res1['headtype'] = $row1->Type;
					$res1['payslip'] = $row1->IncludeInPayslip;
					$res1['additions'] = ($row1->Type==0)?0:1;
					$headarray[]=$res1;
					
					if($row1->Type)
					$total += (int)$row1->HeadAmount;
					else
					$totaldeduction += (int)$row1->HeadAmount;
				}
				
				$res['salarydetail'] = $headarray;
				$res['emptotal'] = round($total);
				$res['emptotaldeduction'] =round($totaldeduction);
				$res['netpay'] = round($total-$totaldeduction);
				
				$data[] = $res;
			}
        }
		
		if ($count == 1) {
           $status =true;
		   $successMsg = SALARYMASTER_MODULE_GETDETAIL_SUCCESS;
        } else {
           $status =false;
		   $errorMsg=SALARYMASTER_MODULE_GETDETAIL_FAILED;
        }
		$result["data"] =$data;
		$result["cur"] =$cur;
		$result['status']=$status;
		$result['currentdate']=Utils::dateformatter(date("Y-m-d"));
		$result['successMsg']=$successMsg;
		$result['errorMsg']=$errorMsg;
		return $result;
    }
	
	public function getSalaryPaidDays($orgid,$field)
	{
		$value=0;
		try{
			$sql1 = "SELECT $field FROM SalaryPaidDays WHERE OrganizationId=?";
			$query1 = $this->db->prepare($sql1);
			$query1->execute(array( $orgid));
			while($row1 = $query1->fetch())
			{
				$value=$row1->$field	;	
			}
		}catch(Exception $e) {
			
		}		
		return $value;
	}
	
	
	///Expences functions starts////
	
	
	public function getheadtype($request)
    {
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		
        $sql = "SELECT * FROM ClaimsHead WHERE OrganizationId = ? ";
        $query = $this->db->prepare($sql);
		try{
			$mid   = $request[0];	//USER ID CONTAINS IN ARRAY FIRST VALUE;
			$orgid = $request[1];	//ORG ID CONTAINS IN ARRAY SECOND VALUE;
			
			$query->execute(array( $orgid ));
			$count =  $query->rowCount();
		}catch(Exception $e) {
			$errorMsg = 'Message: ' .$e->getMessage();
		}
		if($count>=1)
		{
			$status=true;
			$successMsg=$count." record found";
			while($row = $query->fetch())
			{
				$res = array();
				$res['id'] = $row->Id;
				$res['name'] = $row->Name;
				
				$data[] = $res;
			}
        }else{
			$status=true;
			$successMsg = SALARYHEAD_MODULE_GETALL;
		}
		
		//$result["data"] =$data;
		//$result['status']=$status;
		//$result['successMsg']=$successMsg;
		//$result['errorMsg']=$errorMsg;
		
		return $data;
    }
	
	
	public function saveExpense($request)
    {
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;$req_date='';
		$data = array();
        $mid   = $request[0];	//USER ID CONTAINS IN ARRAY FIRST VALUE;
		$orgid = $request[1];	//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$mdate = date("Y-m-d H:i:s");
		$orgname=Utils::getName($orgid,'Organization','Name',$this->db);
		$edate =date('Y-m-d', strtotime(date($request[2])));
		$rdate = "";
		
		$sql1="SELECT Id FROM ClaimsMaster where EmployeeId=? and OrganizationId=? and FromDate=? and ClaimHead=? and ApproverSts in(2,3)";
		$query1 = $this->db->prepare($sql1);
		$query1->execute(array($request[6],$orgid,$edate,$request[4]));
		if($query1->rowCount()>0){
			$result['status']="false1";
			return $result;
		}		
		
		$sql = "INSERT INTO ClaimsMaster ( EmployeeId, FromDate, ClaimHead, Purpose, ApproverSts,  TotalAmt, OrganizationId, CreatedById, CreatedDate, LastModifiedById, LastModifiedDate, OwnerId) VALUES (? ,? , ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$sts = ($request[7]==0)?3:$request[7];
		try{
			$query = $this->db->prepare($sql);
			$query->execute(array($request[6], $edate,($request[4]),$request[3],$sts,$request[5], $orgid, $mid, $mdate, $mid,$mdate, $mid));
			$count =  $query->rowCount();		
			$expenseid = $this->db->lastInsertId();
			
			if ($count == 1) {
			///file insert start///
				try{
					$empcode=Utils::getName($mid,'EmployeeMaster','EmployeeCode',$this->db);
					$new_name="";
					$dir="public/uploads/expense/$orgid";
					if (! is_dir($dir)) {
						mkdir($dir);
						chmod($dir,0777);
					}
					$doc1='expense_'."$expenseid";
					
					$filename="";
					
					if(isset($_FILES['file'])){
						$errors= array();
						$file_name = $_FILES['file']['name'];
						$ext = pathinfo($file_name, PATHINFO_EXTENSION);
						
						if (is_dir($dir)) {
							if ($dh = opendir($dir)) {
								while (($file = readdir($dh)) != false) {
									$filename=$file;
									$ext1 = pathinfo($file_name, PATHINFO_EXTENSION);
									if($filename=="$doc1.$ext1"){
										unlink("public/uploads/expense/$orgid/$filename");
									}
								}
							closedir($dh);
							}
						}
						$new_name='expense_'.$expenseid.'.'.$ext;
						
						$file_size =$_FILES['file']['size'];
						$file_tmp =$_FILES['file']['tmp_name'];
						$file_type=$_FILES['file']['type'];   
						$location="public/uploads/expense/$orgid/";
						
						if($file_size > 2097152){
						//$errors[]='File size must be less than 2 MB';
						}	
							
						if(empty($errors)==true){
							if(move_uploaded_file($file_tmp, $location.$new_name)){
								$count++; 
							}	
						}else{
							print_r($errors);
						}
						
						if ($count >= 1) {
							$status =true;
							$successMsg = "Document uploaded successfully";
							$sql="Update ClaimsMaster set Doc=? where Id=?";
							$query=$this->db->prepare($sql);
							$query->execute(array($new_name,$expenseid));
						} else {
						   $status =false;
						   $errorMsg = EMPLOYEE_MODULE_DOCUPLOAD_FAILED;
						}
					}
				}catch(Exception $e)
				{}	
				//////file  insert closed
				///////Activity Log History File//////////
				$empname=ucwords(Utils::getEmployeeName($request[6],$this->db));
				$claimhead=ucwords(strtolower(Utils::getName($request[4],'ClaimsHead','Name',$this->db)));
				$amt = $request[5];
				$msg="<b>$empname</b> requested for salary expense | Applied On: <b>$edate</b> | Expense Head: <b>$claimhead</b> | Total Amt.: <b>$amt</b>";
				$sql = "INSERT INTO ActivityHistoryMaster ( LastModifiedById, Module, ActionPerformed,  OrganizationId) VALUES (?, ?, ?, ?)";
				$query = $this->db->prepare($sql);
				$query->execute(array($mid, "UBIHRM APP", $msg, $orgid));
				
				$status =true;
				$successMsg = "Your request for expense has been sent!";
				  
				$senior = $this->getApprovalLevelEm($request[6],$orgid, 7);
				if($senior!=0)
				{
					$temp = explode(",", $senior);
					for($i=0; $i<count($temp); $i++)
					{
						if($temp[$i]!=0){
							$sql = "INSERT INTO ClaimApproval ( ClaimId, ApproverId, ApproverSts, CreatedDate ,   OrganizationId) VALUES (?, ?, ?, ?, ?)";
							$query = $this->db->prepare($sql);
							$query->execute(array($expenseid, $temp[$i], 3, $mdate, $orgid));
							$empname=ucwords(Utils::getEmployeeName($request[6],$this->db));
							$orgname=Utils::getName($request[1],'Organization','Name',$this->db);
							$designationId=Utils::getName($request[6],'EmployeeMaster','Designation',$this->db);
							$designationName=ucwords(Utils::getName($designationId, "DesignationMaster","Name", $this->db));
							$departmentId=Utils::getName($request[6],'EmployeeMaster','Department',$this->db);
							$departmentName=ucwords(Utils::getName($departmentId, "DepartmentMaster","Name", $this->db));
							$divisionId=Utils::getName($request[6],'EmployeeMaster','Division',$this->db);
							$divisionName=ucwords(Utils::getName($divisionId, "DivisionMaster","Name", $this->db));
							$empcurency =  Utils::getDivisioncurrency($divisionId,$this->db);
							$head=ucwords(Utils::getName($request[4],'ClaimsHead','Name',$this->db));
							$seniorname=ucwords(Utils::getEmployeeName($temp[$i],$this->db));
							$req_date=date('d/m/Y', strtotime(date($request[2])));
							$amt=$request[5];
							$purpose=ucfirst($request[3]);
							$senioremail=Utils::decode5t(Utils::getName($temp[$i],'EmployeeMaster','CompanyEmail',$this->db));
							
							$approvelink=URL."approvalbymail/expencepproval/$temp[$i]/$orgid/$expenseid/2";
							$rejectlink=URL."approvalbymail/expencepproval/$temp[$i]/$orgid/$expenseid/1";
							$sub="Expense requested by $empname";
							$msg="<table>
											<tr><td>Hello $seniorname,</td></tr>
											<tr><td>$empname has requested for expense.</td></tr>
											<tr><td>Designation: $designationName</td></tr>
											<tr><td>Department: $departmentName</td></tr>
											<tr><td>Expense Head: $head</td></tr>
											<tr><td>Requested Amount: $amt $empcurency</td></tr>
											<tr><td>Purpose: $purpose</td></tr>
											<tr><td>Request date: $req_date</td></tr><br>
											<tr><td>Thanks</td></tr>
											<tr><td>$orgname</td></tr>
									</table>
									<table>
									<tr><td><br/><br/>
										<a href='$approvelink'   style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: green;
										-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px green; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Approve</a>
										&nbsp;&nbsp;
										&nbsp;&nbsp;
										<a href='$rejectlink'  style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: brown;
										-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px brown; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Reject</a>
										<br/><br/>
										</td>															
										</tr>	
									</table>";
									
							Utils::sendMail($senioremail,$empname,$sub,$msg);
							Utils::Trace($sub);
							Utils::Trace($msg);
							Utils::Trace("in expence");	
						}
					}				
				}else{
					$senior = $this->getSeniorId($request[6],$orgid);
					if($senior!=0){
						$temp = explode(",", $senior);
						for($i=0; $i<count($temp); $i++)
						{
							$sql = "INSERT INTO ClaimApproval ( ClaimId, ApproverId, ApproverSts, CreatedDate ,   OrganizationId) VALUES (?, ?, ?, ?, ?)";
							$query = $this->db->prepare($sql);
							$query->execute(array($expenseid, $temp[$i], 3, $mdate, $orgid));
							
							$empname=ucwords(Utils::getEmployeeName($request[6],$this->db));
							$designationId=Utils::getName($request[6],'EmployeeMaster','Designation',$this->db);
							$designationName=ucwords(Utils::getName($designationId, "DesignationMaster","Name", $this->db));
							$departmentId=Utils::getName($request[6],'EmployeeMaster','Department',$this->db);
							$departmentName=ucwords(Utils::getName($departmentId, "DepartmentMaster","Name", $this->db));
							$divisionId=Utils::getName($request[6],'EmployeeMaster','Division',$this->db);
							$divisionName=ucwords(Utils::getName($divisionId, "DivisionMaster","Name", $this->db));
							$empcurency = Utils::getDivisioncurrency($divisionId,$this->db);
							$head=ucwords(Utils::getName($request[4],'ClaimsHead','Name',$this->db));
							$seniorname=ucwords(Utils::getEmployeeName($temp[$i],$this->db));
							$senioremail=Utils::decode5t(Utils::getName($temp[$i],'EmployeeMaster','CompanyEmail',$this->db));
							$req_date=date('d/m/Y', strtotime(date($request[2])));
							$purpose=ucfirst($request[3]);
							$approvelink=URL."approvalbymail/expencepproval/$temp[$i]/$orgid/$expenseid/2";
							$rejectlink=URL."approvalbymail/expencepproval/$temp[$i]/$orgid/$expenseid/1";
							$title="Expense requested by $empname";
							$msg="<table>
											<tr><td>Hello $seniorname,</td></tr>
											<tr><td>$empname has requested for expense.</td></tr>
											<tr><td>Designation: $designationName</td></tr>
											<tr><td>Department: $departmentName</td></tr>
											<tr><td>Expense Head: $head</td></tr>
											<tr><td>Requested Amount: $request[5] $empcurency</td></tr>
											<tr><td>Purpose: $purpose</td></tr>
											<tr><td>Request date: $req_date</td></tr><br>
											<tr><td>Thanks</td></tr>
									</table>
									<table>
										<tr><td><br/><br/>
											<a href='$approvelink'   style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: green;
											-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px green; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Approve</a>
											&nbsp;&nbsp;
											&nbsp;&nbsp;
											<a href='$rejectlink'  style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: brown;
											-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px brown; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Reject</a>
										<br/><br/></td></tr>	
									</table>";
							Utils::sendMail($senioremail,$empname,$title,$msg);
							Utils::Trace($senioremail,$title);
						}
					}else{
						$temp=$this->getHRApproval($orgid);
						$sql = "INSERT INTO ClaimApproval ( ClaimId, ApproverId, ApproverSts, CreatedDate ,   OrganizationId) VALUES (?, ?, ?, ?, ?)";
						$query = $this->db->prepare($sql);
						$query->execute(array($expenseid, $temp, 3, $mdate, $orgid));
						
						$empname=ucwords(Utils::getEmployeeName($request[6],$this->db));
						$designationId=Utils::getName($request[6],'EmployeeMaster','Designation',$this->db);
						$designationName=ucwords(Utils::getName($designationId, "DesignationMaster","Name", $this->db));
						$departmentId=Utils::getName($request[6],'EmployeeMaster','Department',$this->db);
						$departmentName=ucwords(Utils::getName($departmentId, "DepartmentMaster","Name", $this->db));
						$divisionId=Utils::getName($request[6],'EmployeeMaster','Division',$this->db);
						$divisionName=ucwords(Utils::getName($divisionId, "DivisionMaster","Name", $this->db));
						$empcurency = Utils::getDivisioncurrency($divisionId,$this->db);
						$head=ucwords(Utils::getName($request[4],'ClaimsHead','Name',$this->db));
						$seniorname=ucwords(Utils::getEmployeeName($temp,$this->db));
						$senioremail=Utils::decode5t(Utils::getName($temp,'EmployeeMaster','CompanyEmail',$this->db));
						$req_date=date('d/m/Y', strtotime(date($request[2])));
						$purpose=ucfirst($request[3]);
						$approvelink=URL."approvalbymail/expencepproval/$temp[$i]/$orgid/$expenseid/2";
						$rejectlink=URL."approvalbymail/expencepproval/$temp[$i]/$orgid/$expenseid/1";
						$title="Expense requested by $empname";
						$msg="<table>
										<tr><td>Hello $seniorname,</td></tr>
										<tr><td>$empname has requested for expense.</td></tr>
										<tr><td>Designation: $designationName</td></tr>
										<tr><td>Department: $departmentName</td></tr>
										<tr><td>Expense Head: $head</td></tr>
										<tr><td>Requested Amount: $request[5] $empcurency</td></tr>
										<tr><td>Purpose: $purpose</td></tr>
										<tr><td>Request date: $req_date</td></tr><br>
										<tr><td>Thanks</td></tr>
									</table>
									<table>
										<tr><td><br/><br/>
											<a href='$approvelink'   style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: green;
											-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px green; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Approve</a>
											&nbsp;&nbsp;
											&nbsp;&nbsp;
											<a href='$rejectlink'  style='text-decoration:none;padding: 10px 15px; background: #ffffff; color: brown;
											-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px; border: solid 1px brown; text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.4); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), 0 1px 1px rgba(0, 0, 0, 0.2);'>Reject</a>
										<br/><br/></td></tr>	
									</table>";
						Utils::sendMail($senioremail,$empname,$title,$msg);
						Utils::Trace($senioremail,$title);
					}
				}
			} else {
			   $status =false;
			   $errorMsg = DOCUMENT_REQUEST_MODULE_CREATION_FAILED;
			}
		}catch(Exception $e) {
			$errorMsg = 'Message: ' .$e->getMessage();
		}
		$result['status']=$status;
        return $result;
    }
	
	public function getSeniorId($empid, $orgid)
	{
		$id = "0";
		$parentid=$empid;
		if($parentid!="0" && $parentid!="")
		{
			$sql1 = "SELECT ReportingTo FROM EmployeeMaster WHERE OrganizationId = ? and Id in ( $parentid ) and  DOL='0000-00-00' and Is_Delete=0";
			
			$query1 = $this->db->prepare($sql1);
			$query1->execute(array($orgid));
			$parentid="";
			while($row1 = $query1->fetch()){
				$id = $row1->ReportingTo;
			}
		}
		return $id;
	}
	
	public  function getApprovalLevelEm($empid, $orgid, $processtype)
	{
		//processtype 1 for leave, 2 for salary advance, 3 for document request, 4 for resignation, 5 for termination
		$id = "0";
		
		$designation=0;$gethrID=0;
		$gethrID = $this->getHRApproval($orgid);
		if($empid!="0" && $empid!="")
		{
			$sql = "SELECT ReportingTo, Designation FROM EmployeeMaster WHERE OrganizationId = ? and Id = ? ";
			$query = $this->db->prepare($sql);
			$query->execute(array($orgid, $empid));
			while($row = $query->fetch())
			{
				$senior =$seniorid = $row->ReportingTo;
				$designation = $row->Designation;
			}
			
			if($seniorid!=0 && $designation !=0)
			{
				$sql = "SELECT * FROM ApprovalProcess WHERE OrganizationId = ? and (Designation = ? or Designation=0)  and (ProcessType = ? or ProcessType = 0) order by `Designation` desc,`ProcessType` desc limit 1";
				
				$query = $this->db->prepare($sql);
				$query->execute(array($orgid, $designation, $processtype));
				if($query->rowCount()>0)
				{
					$row = $query->fetch();
					$rule = $row->RuleCriteria;
					$hrsts = $row->HrStatus;
					$Approvalupto = $row->Approvalupto;
					
					$Approvalupto1=($Approvalupto-1);
					//echo $Approvalupto."<br>".$Approvalupto1;
					$reportingto = $this->getSeniorIds($empid,$orgid);
					$seniorid = "";
					if($rule !=""){
						$sql1 = "SELECT Id, Designation FROM EmployeeMaster WHERE OrganizationId = ? and DOL='0000-00-00' and Designation in ( $rule )  and Id in ( $reportingto ) and Is_Delete=0 order by FIELD(Designation, $rule)"; /////////
						
						$query1 = $this->db->prepare($sql1);
						$query1->execute(array($orgid));
						while($row1 = $query1->fetch())
						{
							if($row1->Id != 0){
								if($seniorid=="")
								$seniorid = $row1->Id;
								else
								$seniorid .= ",".$row1->Id;
							}
						}
						//////OTHER APPROVER////////
					
						if($row->OtherApprovalSts == 1 && $row->OtherApproverId !=0){
							if($seniorid=="")
								$seniorid = $row->OtherApproverId;
							else
								$seniorid.=','.$row->OtherApproverId;
							
						}
						
						//////////HR APPROVAL//////////////	
						
						if($hrsts!=0){
						
							$temp1 = explode(",", $seniorid);
							for($i=0;$i<count($temp1);$i++)
							{
								if($temp1[$i] == $gethrID){
									unset($temp1[$i]);
								}
							}
							$seniorid.=','.$gethrID;
							
						}
					}
					if($rule ==""){
						$seniorarr = explode(",", $reportingto);
					
						if($Approvalupto != 0){
							for($j=0;$j<count($seniorarr);$j++){
								if($seniorarr[$j] != 0){
									
									if($seniorid=="")
									$seniorid = $seniorarr[$j];
									else
									$seniorid .= ",".$seniorarr[$j];
								
									if($j==$Approvalupto1){
										
									break;
									} 
									
								}else{
									$Approvalupto1=$Approvalupto1+1;
								}
							}
						}
						//////OTHER APPROVER////////
					
						if($row->OtherApprovalSts == 1 && $row->OtherApproverId !=0){
							if($seniorid=="")
								$seniorid = $row->OtherApproverId;
							else
								$seniorid.=','.$row->OtherApproverId;
							
						} 
						
						//////////HR APPROVAL//////////////	
						if($hrsts!=0 && $Approvalupto != 0){
						
							$temp1 = explode(",", $seniorid);
							for($i=0;$i<count($temp1);$i++)
							{
								if($temp1[$i] == $gethrID){
									unset($temp1[$i]);
								}
							}
							$seniorid.=','.$gethrID;
							
						}elseif($hrsts!=0 && $Approvalupto == 0){
							//$seniorid = $gethrID;
							if($seniorid=="")
								$seniorid = $gethrID;
							else
								$seniorid.=','.$gethrID;
						}
					}
					/////End For Set ApprovalUpto /////////
						
				}
			}
		}

		///////IF THERE IS NO APPROVAL SET FOR THE EMPLOYEE THEN BY DEFAULT IT WILL SENT TO IREPORTING TO/////////
		if($seniorid==0)
			$seniorid=$senior;
		
		///////////IF THERE IS NO REPORTING TO THEN REQUEST WILL SEND TO HR///////////////
		if($seniorid==0)
			$seniorid=$gethrID;
		
		$seniorids=explode(",",$seniorid);
		//print_r($seniorids);

		//////////IF APPROVAL IS SET TO ONLY REPORTING TO PERSON THEN WE WILL ADD HR AT END OF THE APPROVAL//////////

		//if(count($seniorids) ==1 && ($processtype==1 || $processtype==8))

		If(count($seniorids) ==1 && $processtype == 7)
			$seniorid.=','.$gethrID;
		
		return $seniorid;
	}
	
	public  function getApprovalLevelEmp($empid, $processtype, $orgid)
	{
		$id = "0";
		$designation=0;
		$gethrID=0;
		$gethrID=$this->getHRApproval($orgid);
		if($empid!="0" && $empid!=""){
			$sql = "SELECT ReportingTo, Designation FROM EmployeeMaster WHERE OrganizationId = ? and Id = ? ";
			$query = $this->db->prepare($sql);
			$query->execute(array($orgid, $empid));
			while($row = $query->fetch()){
				$seniorid = $row->ReportingTo;
				$designation = $row->Designation;
			}
			
			if($seniorid!=0 && $designation !=0){
				$sql = "SELECT RuleCriteria, Designation,HrStatus, Approvalupto FROM ApprovalProcess WHERE OrganizationId = ? and (Designation = ? or Designation=0)  and (ProcessType = ? or ProcessType = 0 )order by (`Designation` or`ProcessType`)  desc limit 1";
				$query = $this->db->prepare($sql);
				$query->execute(array($orgid, $designation, $processtype));
				if($query->rowCount()>0){
					$row = $query->fetch();
					$rule = $row->RuleCriteria;
					$sts = $row->HrStatus;
					$Approvalupto = $row->Approvalupto;
					$Approvalupto1=($Approvalupto-1);
					//echo $Approvalupto."<br>".$Approvalupto1;
					$reportingto = $this->getSeniorIds($empid, $orgid);
					$seniorid = "";
					if($rule !=""){
						$sql = "SELECT Id, Designation FROM EmployeeMaster WHERE OrganizationId = ? and DOL='0000-00-00' and Designation in ( $rule )  and Id in ( $reportingto ) and Is_Delete=0 order by FIELD(Designation, $rule)"; /////////
						///////////sts=0 for all the designation and employee,if sts=1 then hierarchy employee will come///////
						//if($sts==0)
						//$sql = "SELECT Id, Designation FROM EmployeeMaster WHERE OrganizationId = ? and DOL='0000-00-00' and Designation in ( $rule )";
						//$gethrID=0;
						$query = $db->prepare($sql);
						$query->execute(array($orgid));
						while($row = $query->fetch()){
							if($seniorid=="")
							$seniorid = $row->Id;
							else
							$seniorid .= ",".$row->Id;
						}
					}
					
					if($rule ==""){
						$str = ltrim($reportingto, '0,');
						//$number = rtrim($str, "0");
						//print_r($number);
						$senioridcount=$str;
						$seniorarr = explode(",", $senioridcount);
						/////For Set ApprovalUpto /////////
						//print_r($seniorarr);
						for($j=0;$j<count($seniorarr);$j++){
							if($seniorid=="")
							$seniorid = $seniorarr[$j];
							else
							$seniorid .= ",".$seniorarr[$j];
							if($j==$Approvalupto1){
								break;
							} 
						}
					}
					/////End For Set ApprovalUpto /////////
					if($sts!=0){
						$temp1 = explode(",", $seniorid);
						for($i=0;$i<count($temp1);$i++)
						{
							if($temp1[$i] == $gethrID){
								unset($temp1[$i]);
							}
						}
						$seniorid.=','.$gethrID;
					}else{
						$seniorid;	
					}
				}
			}
		}
		$seniorids=explode(",",$seniorid);
		if(count($seniorids) ==1 && ($processtype==1 || $processtype==8))
			$seniorid.=','.$gethrID;
		return $seniorid;
	}
	
	public function getSeniorIds($empid, $orgid)
	{
		$ids = "0";
		$parentid=$empid;
		if($parentid!="0" && $parentid!=""){
			while($parentid!=""){
				$sql1 = "SELECT ReportingTo FROM EmployeeMaster WHERE OrganizationId = ? and Id in ( $parentid ) and  DOL='0000-00-00' and Is_Delete=0";
				$query1=$this->db->prepare($sql1);
				$query1->execute(array($orgid));
				$parentid="";
				while($row1 = $query1->fetch())
				{
					if($parentid==""){
						$parentid = $row1->ReportingTo;
					}else{
						$parentid .= ", ".$row1->ReportingTo;
					}
					
					if($ids==""){
						$ids = $row1->ReportingTo;
					}else{
						$ids .= ",".$row1->ReportingTo;
					}
				}
			}
		}
		return $ids;
	}

	public  function getHRApproval($orgid)
	{
		$index=0;
		$ids=array();
		$sql = "SELECT EmployeeId FROM UserMaster WHERE OrganizationId = ? and HRSts=1 ";	
		$query = $this->db->prepare($sql);
		$query->execute(array($orgid));
		$parentid="";
		while($row = $query->fetch())
		{
			$ids = $row->EmployeeId;
		}
		return $ids;
	}
	
	public function uploadDocument($request)
	{
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		$mid   = $request[0];	//USER ID CONTAINS IN ARRAY FIRST VALUE;
		$orgid = $request[1];	//ORG ID CONTAINS IN ARRAY SECOND VALUE;
		$empid = $request[2];
		$expenseid = $request[4];
		try{
			$empcode=Utils::getName($empid,'EmployeeMaster','EmployeeCode',$this->db);
			$new_name="";
			$dir="public/uploads/$orgid/$empcode";
		
			if (! is_dir($dir)) {
				mkdir($dir);
				chmod($dir,0755);	
			}
			$doc1='expense_'."$expenseid";
			$filename="";
			if(isset($_FILES['file0'])){
				$errors= array();
				$file_name = $_FILES['file0']['name'];
				$ext = pathinfo($file_name, PATHINFO_EXTENSION);
				
				if (is_dir($dir)) {
					if ($dh = opendir($dir)) {
						while (($file = readdir($dh)) != false) {
							$filename=$file;
							$ext1 = end((explode(".", $filename)));
							if($filename=="$doc1.$ext1")
							{
								unlink("public/uploads/$orgid/$empcode/$filename");
							}
						}
					closedir($dh);
					}
				}
				
				$new_name='expense_'.$expenseid.'.'.$ext;
				$file_size =$_FILES['file0']['size'];
				$file_tmp =$_FILES['file0']['tmp_name'];
				$file_type=$_FILES['file0']['type'];   
				$location="public/uploads/$orgid/$empcode/";
				if($file_size > 2097152){
				//$errors[]='File size must be less than 2 MB';
				}	
				
				if(empty($errors)==true){
					if(move_uploaded_file($file_tmp, $location.$new_name)){
					$count++; }
				}else{
					print_r($errors);
				}
				
				if ($count >= 1) {
					$status =true;
					$successMsg = "Document uploaded successfully";
					$sql="Update ClaimsMaster set Doc=? where Id=?";
					$query=$this->db->prepare($sql);
					$query->execute(array($new_name,$expenseid));
				} else {
				   $status =false;
				   $errorMsg = EMPLOYEE_MODULE_DOCUPLOAD_FAILED;
				}
			}
		}catch(Exception $e)
		{}		
		$result["data"] =$data;
		$result['status']=$status;
		$result['successMsg']=$successMsg;
		$result['errorMsg']=$errorMsg;
        return $result;
	}
	
	public function getcountryName($id)
	{
		$name ="";
		$sql = "SELECT  Name  FROM CountryMaster WHERE Id = ? ";
		$query = $this->db->prepare($sql);
		try{
			$query->execute(array($id));
			while($row = $query->fetch())
			{
				$name = $row->Name;
			}
		}catch(Exception $e) {
		}
		return $name;
	}
	
	public function getExpenseDetail($arr)
    {
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		$datearr=array();
		$id=$arr[0];
		$orgid = $arr[1];
		$date1 =$arr[2];
		try{
			$sql = "SELECT * FROM ClaimsMaster WHERE EmployeeId = :id and FromDate= :date1 order by FromDate desc";
			$query = $this->db->prepare($sql);			
			$query->execute(array(':id' => $id ,':date1' => $date1));
			$count =  $query->rowCount();
			if($count>=1){
				$status=true;
				while($row = $query->fetch()){
					$res = array();
					$res['id'] = $row->Id;
					$res['employeeid'] = (int)$row->EmployeeId;
					$res['employee'] = $this->getName($row->EmployeeId,'EmployeeMaster');
					$res['fromdate'] =date("d M Y", strtotime($row->FromDate));
					$res['purpose'] = $row->Purpose;
					$res['totalclaim'] = $row->TotalAmt;
					$res['ClaimHead']=0;
					if($row->ClaimHead!=0){
						$res['ClaimHead'] = $row->ClaimHead;
						$res['ClaimHeadname'] = Utils::getName($row->ClaimHead,"ClaimsHead","Name",$this->db);
					}
					$res['approverid'] =Utils::getEmployeeName( $row->ApproverId,$this->db);
					$res['appsts'] = ($row->ApproverSts==0)?3:$row->ApproverSts;
					$image = $row->Doc;
					$empcode=Utils::getName($row->EmployeeId,'EmployeeMaster','EmployeeCode',$this->db);
					if($image!=""){
						if (file_exists( "public/uploads/$orgid/$empcode/$image")) {
							$res['doc']= URL."public/uploads/$orgid/$empcode/$image". "?img=" .rand(1,100);
						}
					}
					$sql1 = "SELECT Department, Designation,Division FROM EmployeeMaster WHERE Id = ?";
					$query1 = $this->db->prepare($sql1);
					$query1->execute(array( $row->EmployeeId ));
					while($row1 = $query1->fetch()){
						$res['department'] = Utils::getName($row1->Department, "DepartmentMaster","Name", $this->db);
						$res['designation'] = Utils::getName($row1->Designation, "DesignationMaster","Name", $this->db);
						$res['division'] = Utils::getName($row1->Division, "DivisionMaster","Name", $this->db);
						$res['empcurency'] =  Utils::getDivisioncurrency($row1->Division,$this->db);
					}
					$data[] = $res;
				}
			} else {
			   $status =false;
			   $errorMsg = DOCUMENT_REQUEST_MODULE_GETDETAIL_FAILED;
			}
		}catch(Exception $e) {
			$status =false;
			$errorMsg = 'Message: ' .$e->getMessage();
		}
		$result["data"] =$data;
		$result['status']=$status;
		$result['successMsg']=$successMsg;
		$result['errorMsg']=$errorMsg;
		return $data;
    }
	
	public function getExpenseDetailbydate($arr)
    {
		$result = array();
		$count=0; $errorMsg=""; $successMsg=""; $status=false;
		$data = array();
		$datearr=array();
		$id=$arr[0];
		$orgid = $arr[1];
		try{
			//$id=Utils::decode5t($id);
			$sql = "SELECT distinct(FromDate) FROM ClaimsMaster WHERE EmployeeId = :id order by FromDate desc";
			$query = $this->db->prepare($sql);			
			$query->execute(array(':id' => $id ));
			$count =  $query->rowCount();
			if($count>=1){
				$status=true;
				while($row = $query->fetch()){
					$res = array();
				/*	$res['id'] = $row->Id;
					$res['employeeid'] = (int)$row->EmployeeId;
					$res['employee'] = $this->getName($row->EmployeeId,'EmployeeMaster');*/
					//$res['fromdate'] = Utils::dateformatter($row->FromDate);
					$res['fromdate'] =date("d-M-Y", strtotime($row->FromDate));
					$res['Fdate'] =$row->FromDate;
					/*$res['purpose'] = $row->Purpose;
					$res['totalclaim'] = $row->TotalAmt;
					$res['ClaimHead']=0;
					if($row->ClaimHead!=0){
						$res['ClaimHead'] = $row->ClaimHead;
						$res['ClaimHeadname'] = Utils::getName($row->ClaimHead,"ClaimsHead","Name",$this->db);
					}
					$res['approverid'] =Utils::getEmployeeName( $row->ApproverId,$this->db);
					$res['appsts'] = ($row->ApproverSts==0)?3:$row->ApproverSts;
					
					
					$image = $row->Doc;
					$empcode=Utils::getName($row->EmployeeId,'EmployeeMaster','EmployeeCode',$this->db);
					if($image!=""){
						if (file_exists( "public/uploads/$orgid/$empcode/$image")) {
							$res['doc']= URL."public/uploads/$orgid/$empcode/$image". "?img=" .rand(1,100);
						}
					}
					$sql1 = "SELECT Department, Designation,Division FROM EmployeeMaster WHERE Id = ?";
					$query1 = $this->db->prepare($sql1);
					$query1->execute(array( $row->EmployeeId ));
					while($row1 = $query1->fetch())
					{
						$res['department'] = Utils::getName($row1->Department, "DepartmentMaster","Name", $this->db);
						$res['designation'] = Utils::getName($row1->Designation, "DesignationMaster","Name", $this->db);
						$res['division'] = Utils::getName($row1->Division, "DivisionMaster","Name", $this->db);
						
						$res['empcurency'] =  Utils::getDivisioncurrency($row1->Division,$this->db);
						
					}*/
					
					$data[] = $res;
				}
			} else {
			   $status =false;
			   $errorMsg = DOCUMENT_REQUEST_MODULE_GETDETAIL_FAILED;
			}
		}catch(Exception $e) {
			$status =false;
			$errorMsg = 'Message: ' .$e->getMessage();
		}
		
		$result["data"] =$data;
		$result['status']=$status;
		$result['successMsg']=$successMsg;
		$result['errorMsg']=$errorMsg;
		
		return $data;
    }
	
	public function savesurvey()
    {
		$data=array();
		$city	= isset($_REQUEST['city'])?trim($_REQUEST['city']):'';
		$country	= isset($_REQUEST['country'])?trim($_REQUEST['country']):'';
		$phone	= isset($_REQUEST['phone'])?trim($_REQUEST['phone']):'';
		$org_name	= isset($_REQUEST['org_name'])?trim($_REQUEST['org_name']):'';
		$first_name	= isset($_REQUEST['first_name'])?trim($_REQUEST['first_name']):'';
		$comments	= isset($_REQUEST['comments'])?trim($_REQUEST['comments']):'';
		$email	= isset($_REQUEST['email'])?trim($_REQUEST['email']):'';
		$trialorgid	= isset($_REQUEST['trialorgid'])?trim($_REQUEST['trialorgid']):'';
		$your_area	= isset($_REQUEST['your_area'])?trim($_REQUEST['your_area']):'';
		$empno	= isset($_REQUEST['empno'])?trim($_REQUEST['empno']):'';
		$Attendance	= isset($_REQUEST['Attendance'])?trim($_REQUEST['Attendance']):'';
		$Leave 	= isset($_REQUEST['Leave'])?trim($_REQUEST['Leave']):'';
		$Payroll 	= isset($_REQUEST['Payroll'])?trim($_REQUEST['Payroll']):'';
		$Timesheet	= isset($_REQUEST['Timesheet'])?trim($_REQUEST['Timesheet']):'';
		$Expense	= isset($_REQUEST['Expense'])?trim($_REQUEST['Expense']):'';
		$Coreubihrm	= isset($_REQUEST['Coreubihrm'])?trim($_REQUEST['Coreubihrm']):'';
		$Performance	= isset($_REQUEST['Performance'])?trim($_REQUEST['Performance']):'';
		$Others	= isset($_REQUEST['Others'])?trim($_REQUEST['Others']):'';
		$date  = date('Y-m-d');
		if($Coreubihrm!=''){$comma1 = ",";}else{$comma1 = "";}
		if($Attendance!=''){$comma2 = ",";}else{$comma2 = "";}
		if($Leave!=''){$comma3 = ",";}else{$comma3 = "";}
		if($Payroll!=''){$comma4 = ",";}else{$comma4 = "";}
		if($Timesheet!=''){$comma5 = ",";}else{$comma5 = "";}
		if($Performance!=''){$comma6 = ",";}else{$comma6 = "";}
		if($Expense!=''){$comma7 = ",";}else{$comma7 = "";}
		
	 	$modules_required = $Attendance."".$comma2."".$Leave."".$comma3."".$Payroll."".$comma4."".$Timesheet."".$comma5."".$Performance."".$comma6."".$Expense;
		
		$mdate=date("Y-m-d");
		$mdate1=date("Y-m-d h:i:s");
		$start_date=date("Y-m-d");
		$end_date="";$trialdays=0;$count1=0;$count2=0;$count3=0;$count4=0;$count5=0;$count6=0;
		
		$sql="SELECT `trial_days` FROM  `ubihrm_login` ";
		$query = $this->db->prepare($sql);
		$query->execute(array());
		if($row = $query->fetch()){
			$trialdays=(int)$row->trial_days;
			$end_date = date('Y-m-d',strtotime('+'.$trialdays.' day', strtotime($start_date)));
		}
		
		$sql6="SELECT * from TrialOrganization WHERE Id=$trialorgid and mail_varified=0";
		$query6 = $this->db->prepare($sql6);               
		$query6->execute(array());
		$count6 =  $query6->rowCount();
		if($count6==1)
		{
			$sql1="Update TrialOrganization Set NoOfEmp=$empno, mail_varified=1, start_date='$start_date', end_date='$end_date', PreferredTimeToCall='$your_area', ModulesRequired='$modules_required' WHERE Id=$trialorgid";
		
			$query1 = $this->db->prepare($sql1);               
			$query1->execute(array());
			$count1 =  $query1->rowCount();
			if($count1>0)
			{  
				if($country=='India' || $country=='93')
				{
					$sql2="SELECT Id FROM `Organization` WHERE Trial_sts=1 ";
					$query2 = $this->db->prepare($sql2);               
					$query2->execute(array());
					$count2 =  $query2->rowCount();
					if($count2==1)
					{
						$row2 = $query2->fetch();
						$org_id = $row2->Id;
						
						$sql1="SELECT Id FROM `DepartmentMaster` WHERE OrganizationId=? and Name=?";
						$query1= $this->db->prepare($sql1);               
						$query1->execute(array($org_id, 'Dummy Department'));
						$count1 =  $query1->rowCount();
						if($count1>0){
							$row1 = $query1->fetch();
							$dept_id=$row1->Id;
						}else{
							$query11 = "INSERT INTO `DepartmentMaster`(`Name`, `CreatedDate`, `LastModifiedDate`, `OrganizationId`) VALUES (?,?,?,?)";
							$query111= $this->db->prepare($query11);   
							$query111->execute(array('Dummy Department',$date,$date,$org_id));
							$res11   = $query111->rowCount();
							$dept_id=$this->db->lastInsertId();
						}
						
						$sql2="SELECT Id FROM `ShiftMaster` WHERE OrganizationId=? and Name=?";
						$query2= $this->db->prepare($sql2);               
						$query2->execute(array($org_id, 'Dummy shift'));
						$count2 =  $query2->rowCount();
						if($count2>0){
							$row2 = $query2->fetch();
							$shift_id=$row2->Id;
						}else{
							$query12 ="INSERT INTO `ShiftMaster`(`Name`, `TimeIn`, `TimeOut`, `TimeInGrace`, `TimeOutGrace`, `TimeInBreak`, `TimeOutBreak`, `OrganizationId`, `CreatedDate`, `LastModifiedDate`, `BreakInGrace`, `BreakOutGrace`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
							$query122 = $this->db->prepare($query12);   
							$query122->execute(array('Dummy shift','09:30:00','18:30:00','09:45:00','18:45:00','13:00:00','13:45:00',$org_id,$date,$date,'13:15:00','14:00:00'));
							$res12   = $query122->rowCount();
							$shift_id=$this->db->lastInsertId();
						}
						
						$sql3="SELECT Id FROM `DesignationMaster` WHERE OrganizationId=? and Name=?";
						$query3= $this->db->prepare($sql3);               
						$query3->execute(array($org_id, 'Dummy Designation'));
						$count3 =  $query3->rowCount();
						if($count3>0){
							$row3 = $query3->fetch();
							$desg_id=$row3->Id;
						}else{
							$query13 ="INSERT INTO `DesignationMaster`(`Name`, `OrganizationId`, `CreatedDate`, `LastModifiedDate`, `Description`) VALUES (?,?,?,?,?)";
							$query133 = $this->db->prepare($query13);   
							$query133->execute(array('Dummy Designation',$org_id,$date,$date,'Dummy Designation'));
							$res13   = $query133->rowCount();
							$desg_id=$this->db->lastInsertId();
						}
						
						$sql3="INSERT INTO `EmployeeMaster`(`FirstName`, `DOJ`, `CurrentContactNumber`, `CurrentEmailId`, `CreatedDate`, `LastModifiedDate`, `OrganizationId`, `CompanyEmail`, `countrycode`, Department, Designation, Shift) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
						$query3 = $this->db->prepare($sql3);               
						$query3->execute(array($first_name, $mdate, Utils::encode5t($phone), Utils::encode5t($email), $mdate, $mdate1, $org_id, Utils::encode5t($email), $country, $dept_id, $desg_id, $shift_id));
						$count3 =  $query3->rowCount();
						$empid=$this->db->lastInsertId();
						if($count3>0)
						{
							$sql4="SELECT Id  FROM `Userprofile` WHERE `OrganizationId`=? and `AdminSts`=1";
							$query4 = $this->db->prepare($sql4);               
							$query4->execute(array($org_id));
							$count4 =  $query4->rowCount();
							if($count4>0)
							{
								$row4 = $query4->fetch();
								$userprofileid = $row4->Id;
							}
							
							$sql5="INSERT INTO `UserMaster`(`EmployeeId`, `Password`, `Username`, `userprofile`, `username_mobile`, `OrganizationId`, `CreatedDate`, `LastModifiedDate`,`AdminSts`, `VisibleSts`, `trial_OrganizationId`) VALUES(?,?,?,?,?,?,?,?,?,?,?)";
							$query5 = $this->db->prepare($sql5);               
							$query5->execute(array($empid, Utils::encode5t('123456'), Utils::encode5t($email), $userprofileid, Utils::encode5t($phone), $org_id, $mdate, $mdate1, 1, 1,$trialorgid));
							$count5 =  $query5->rowCount();
							Utils::Trace("COUNT5 ".$count5);
							if($count5>0)
							{
								$msg="<html>
												<p>UBIHRM survey is completed by ".ucwords($first_name)."</p>
												<p>Company name: ".ucwords($org_name)."</p>
												<p>Phone: ".$phone."</p>
												<p>Email: ".$email."</p>
												<p>City: ".$city."</p>
												<p>Country: ".$country."</p>
												<p>Requirments: ".$comments."</p>
												<p>Preferred time to call: ".$your_area."</p>
												<p>No. of employee: ".$empno."</p>
												<p>Modules: ".$modules_required."</p>
												<!-- <p>Modules: ".$Attendance.",".$Leave.",".$Expense.",".$Payroll.",".$Timesheet.",".$Performance."</p> --><br/>
											  
												<p>Cheers,<br/>Team ubiHRM<br/>Tel/ Whatsapp(India): +91 7773000234<br/>Tel/ Whatsapp(Overseas): +971 55-5524131<br/>Email: ubihrmsupport@ubitechsolutions.com</p>
											</html>";
								$subject="ubiHRM Survey is completed";
							  
								//$sts1=Utils::sendmail("pratibha@ubitechsolutions.com",'UBIHRM',$subject,$msg);
								$sts2=Utils::sendmail("ubihrmsupport@ubitechsolutions.com",'UBIHRM',$subject,$msg);
								$sts3=Utils::sendmail("reach@ubitechsolutions.com",'UBIHRM',$subject,$msg);
								$sts4=Utils::sendmail("sales@ubitechsolutions.com",'UBIHRM',$subject,$msg);
									  
								Utils::Trace("survey msg");
								Utils::Trace($msg);
								  
								  /* $msg1="<html>
								  <p>Hello ".ucwords($first_name).",</p>
								  <p>Greetings from ubiHRM Team! </p>
								  <p>You have registered successfully with Admin profile on ubiHRM App for ".ucwords($org_name)." </p>
								  <p>Login details for Web Admin Panel and Mobile App </p>
								  <p>Link: https://demoaccount.ubihrm.com </p>
								  <p>Username(Email): ".$email." </p>
								  <p>Password: 123456 </p>
								  <p>Or </p>
								  <p>Username(Phone No.): ".$phone." </p>
								  <p>Password: 123456 </p>
								  <!--<p>Login details for Web Admin Panel </p>
								  <p>Link: https://demoaccount.ubihrm.com </p>
								  <p>Username(Email): ".$email." </p>
								  <p>Password: 123456 </p>
								  <p>Or </p>
								  <p>Username(Phone No.): ".$phone." </p>
								  <p>Password: 123456 </p>-->
								  <p>Cheers,<br/>Team ubiHRM<br/>Tel/ Whatsapp(India): +91 7773000234<br/>Tel/ Whatsapp(Overseas): +971 55-5524131<br/> Email: ubihrmsupport@ubitechsolutions.com</p>
								  </html>";
								  $subject1="You are registered with Admin profile on ubiHRM App";
								  
								  $sts=Utils::sendmail($email,'UBIHRM',$subject1,$msg1);

								  Utils::Trace("survey msg1");
								  Utils::Trace($msg1); */

								  /* if($sts2 && $sts)
								  { 
									//	echo "Thanks for sharing your requirements. You will soon hear from us.";

										//Kindly refer to our Get Started Guide to start. Need more help? Contact us or View our Channel and learn about the key features";
								  } */
								  
							}
						}
					}
				}
				else
				{
					$sql2="SELECT Id FROM `Organization` WHERE Trial_sts=2 ";
					$query2 = $this->db->prepare($sql2);               
					$query2->execute(array());
					$count2 =  $query2->rowCount();
					if($count2==1)
					{
						$row2 = $query2->fetch();
						$org_id = $row2->Id;
						
						$sql3="INSERT INTO `EmployeeMaster`(`FirstName`, `DOJ`, `CurrentContactNumber`, `CurrentEmailId`, `CreatedDate`, `LastModifiedDate`, `OrganizationId`, `CompanyEmail`, `countrycode`) VALUES (?,?,?,?,?,?,?,?,?)";
						$query3 = $this->db->prepare($sql3);               
						$query3->execute(array($first_name, $mdate, Utils::encode5t($phone), Utils::encode5t($email), $mdate, $mdate1, $org_id, Utils::encode5t($email), $country));
						$count3 =  $query3->rowCount();
						$empid=$this->db->lastInsertId();
						if($count3>0)
						{
							$sql4="SELECT Id  FROM `Userprofile` WHERE `OrganizationId`=? and `AdminSts`=1";
							$query4 = $this->db->prepare($sql4);               
							$query4->execute(array($org_id));
							$count4 =  $query4->rowCount();
							if($count4>0)
							{
								$row4 = $query4->fetch();
								$userprofileid = $row4->Id;
							}
							
							$sql5="INSERT INTO `UserMaster`(`EmployeeId`, `Password`, `Username`, `userprofile`, `username_mobile`, `OrganizationId`, `CreatedDate`, `LastModifiedDate`,`AdminSts`, `VisibleSts`, `trial_OrganizationId`) VALUES(?,?,?,?,?,?,?,?,?,?,?)";
							$query5 = $this->db->prepare($sql5);               
							$query5->execute(array($empid, Utils::encode5t('123456'), Utils::encode5t($email), $userprofileid, Utils::encode5t($phone), $org_id, $mdate, $mdate1, 1, 1,$trialorgid));
							$count5 =  $query5->rowCount();
							Utils::Trace("COUNT5 ".$count5);
							if($count5>0)
							{
								$msg="<html>
												<p>UBIHRM survey is completed by ".ucwords($first_name)."</p>
												<p>Company name: ".ucwords($org_name)."</p>
												<p>Phone: ".$phone."</p>
												<p>Email: ".$email."</p>
												<p>City: ".$city."</p>
												<p>Country: ".$country."</p>
												<p>Requirments: ".$comments."</p>
												<p>Preferred time to call: ".$your_area."</p>
												<p>No. of employee: ".$empno."</p>
												<p>Modules: ".$modules_required."</p>
												<!--<p>Modules: ".$Attendance.",".$Leave.",".$Expense.",".$Payroll.",".$Timesheet.",".$Performance."</p>--><br/>
												  
												<p>Cheers,<br/>Team ubiHRM<br/>Tel/ Whatsapp(India): +91 7773000234<br/>Tel/ Whatsapp(Overseas): +971 55-5524131<br/>Email: ubihrmsupport@ubitechsolutions.com</p>
											</html>";
								$subject="ubiHRM Survey is completed";
								  
								$sts1=Utils::sendmail("anita@ubitechsolutions.com",'UBIHRM',$subject,$msg);
								//$sts2=Utils::sendmail("ubihrmsupport@ubitechsolutions.com",'UBIHRM',$subject,$msg);
								$sts3=Utils::sendmail("reach@ubitechsolutions.com",'UBIHRM',$subject,$msg);
								//$sts4=Utils::sendmail("sales@ubitechsolutions.com",'UBIHRM',$subject,$msg);
								Utils::Trace("survey msg");
								Utils::Trace($msg);
								  
								  /* $msg1="<html>
								  <p>Hello ".ucwords($first_name).",</p>
								  <p>Greetings from ubiHRM Team! </p>
								  <p>You have registered successfully with Admin profile on ubiHRM App for ".ucwords($org_name)." </p>
								  <p>Login details for Web Admin Panel and Mobile App </p>
								  <p>Link: https://ubidemo.ubihrm.com </p>
								  <p>Username(Email): ".$email." </p>
								  <p>Password: 123456 </p>
								  <p>Or </p>
								  <p>Username(Phone No.): ".$phone." </p>
								  <p>Password: 123456 </p>
								  <!--<p>Login details for Web Admin Panel </p>
								  <p>Link: https://ubidemo.ubihrm.com </p>
								  <p>Username(Email): ".$email." </p>
								  <p>Password: 123456 </p>
								  <p>Or </p>
								  <p>Username(Phone No.): ".$phone." </p>
								  <p>Password: 123456 </p>-->
								  <p>Cheers,<br/>Team ubiHRM<br/>Tel/ Whatsapp(India): +91 7773000234<br/>Tel/ Whatsapp(Overseas): +971 55-5524131<br/> Email: ubihrmsupport@ubitechsolutions.com</p>
								  </html>";
								  $subject1="You are registered with Admin profile on ubiHRM App";
								  
								  $sts=Utils::sendmail($email,'UBIHRM',$subject1,$msg1);

								  Utils::Trace("survey msg1");
								  Utils::Trace($msg1); */

								  /* if($sts2 && $sts)
								  { 
									//	echo "Thanks for sharing your requirements. You will soon hear from us.";

										//Kindly refer to our Get Started Guide to start. Need more help? Contact us or View our Channel and learn about the key features";
								  } */
								  
							}
						}
					}
				}
			}
		}
		if($count5>0){
			$data['status']=1;
		}
		else{
			if($count6==0){
			 $data['status']=2;
			}else{
			 $data['status']=0;
			} 
		}
		 return $data;
	}
	
	
}
	
