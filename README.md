myselect
========

SQL based log statistics and analysis tool



you can use sql syntax to  analyze your log file which is a general textfile with myselect.myselect assume  log line the 
record in the database,and assume  item splited by space the field in the database. you can use myselect as follow


log line:

    198.52.103.14 - - [29/Jun/2014:00:17:11 +0800] "GET /q/1403060495509100 HTTP/1.1" 200 26788   
    "http://wenda.so.com/q/1403060495509100" "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0; .NET 
    CLR 2.0.50727)" 221 0.532             
                 
                 

find the most client ip:

    $ myselect  'select count(\$1),\$1 from accesstest.log  group by \$1 order by count($1) desc limit 10'
    14	111.13.65.251
    13	10.141.88.248
    12	10.141.88.239
    10	10.141.88.250
    9	121.226.135.115
    8	10.141.88.241
    8	10.141.88.249
    8	222.74.246.190
    7	211.149.165.150
    6	61.174.51.174



more introduction here : http://blog.csdn.net/micweaver/article/details/37579153
