import 'package:flutter/material.dart';
import '../services/api.dart';
import '../models/contact.dart';

class ContactsScreen extends StatelessWidget {
  final ChatApi api;
  final Future<List<Contact>> _contactsFuture;
  ContactsScreen({super.key, required this.api})
    : _contactsFuture = api.fetchContacts();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Messages')),
      body: FutureBuilder<List<Contact>>(
        future: _contactsFuture,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            print(snap.error);
            return Center(child: Text('Erreur : ${snap.error}'));
          }
          final contacts = snap.data ?? const <Contact>[];
          if (contacts.isEmpty) {
            return const Center(child: Text('Aucun contact'));
          }
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Padding(
                padding: EdgeInsets.fromLTRB(16, 24, 16, 8),
                child: Text(
                  'CONTACT',
                  style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 2,
                  ),
                ),
              ),
              Expanded(
                child: ListView.separated(
                  itemCount: contacts.length,
                  separatorBuilder: (_, __) =>
                      Divider(color: Colors.white.withOpacity(.1), height: 1),
                  itemBuilder: (_, i) {
                    final c = contacts[i];
                    return ListTile(
                      leading: CircleAvatar(
                        backgroundImage: NetworkImage(c.avatarUrl),
                      ),
                      title: Text(c.name),
                      subtitle: Text(c.phone),
                      onTap: () => Navigator.pop(context),
                    );
                  },
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
