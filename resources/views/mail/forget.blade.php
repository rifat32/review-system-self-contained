<!DOCTYPE html>
<html>
<head>
 <title>Welcome to {{env('APP_NAME')}}</title>
</head>
<body>

 <h1>use this link to update password. it will expire in 1 day. </h1>

 <h1> here is the link. <a href="http://localhost:3000/fotget-password/{{$token}}">link</a></h1>


</body>
</html>
