// Stub file for ChatDetailScreen
import 'package:flutter/material.dart';

class ChatDetailScreen extends StatelessWidget {
  final String? chatId;
  final String? peerId;
  final String? peerName;
  final String? peerAvatar;
  final String? receiverId;
  final String? receiverName;
  final String? receiverAvatar;

  const ChatDetailScreen({
    super.key,
    this.chatId,
    this.peerId,
    this.peerName,
    this.peerAvatar,
    this.receiverId,
    this.receiverName,
    this.receiverAvatar,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(peerName ?? receiverName ?? 'Chat')),
      body: const Center(child: Text('Chat')),
    );
  }
}
