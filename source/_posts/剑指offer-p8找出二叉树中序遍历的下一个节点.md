---
title: '剑指offer p8:找出二叉树中序遍历的下一个节点'
tags: []
id: '2100'
categories:
  - - self_culture
    - 2020刷题记录
date: 2020-03-08 07:51:29
---

使用两种方法实现。

方法一：中序遍历二叉树，记录指定节点，当遍历到下一个节点时返回

时间复杂度o(n)

方法二:

1）当指定节点有右子树时，返回其右子树的第一个中序遍历节点

2）当指定节点无右子树时，如果其是父节点的左节点，则返回其父节点

3）当指定节点无右子树，且是其父节点的右节点，则一直向上找父节点，当找到某个父节点是其父节点的左节点时返回

时间复杂度o(logn)

代码实现:

```
class BinNode:
    def __init__(self, val, left=None, right=None, father=None):
        self.val = val
        self.left = left
        self.right = right

        if self.left:
            self.left.father = self
        if self.right:
            self.right.father = self

        self.father = father

    def __str__(self):
        return self.val

def no_traverse_next(target):
    if target.right:
        r = target.right
        while r.left:
            r = r.left
        return r

    f = target
    ff = f.father
    while f and ff:
        if ff.left == f:
            return ff
        else:
            f = ff
            ff = ff.father
    return None


def inorder_traverse_next(root, target):
    prev, node, stack = None, root, []
    while node or stack:
        if node:
            stack.append(node)
            node = node.left
        else:
            n = stack.pop()
            if n == target:
                prev = n
            if prev and n != target:
                return n
            node = n.right
    return None

if __name__ == "__main__":
    i = BinNode('i')
    h = BinNode('h')
    g = BinNode('g')
    f = BinNode('f')
    e = BinNode('e', h, i)
    d = BinNode('d')
    c = BinNode('c', f, g)
    b = BinNode('b', d, e)
    a = BinNode('a', b, c)

    print(inorder_traverse_next(a, i))
    print(inorder_traverse_next(a, b))
    print(inorder_traverse_next(a, g))

    print(no_traverse_next(i))
    print(no_traverse_next(b))
    print(no_traverse_next(g)
```